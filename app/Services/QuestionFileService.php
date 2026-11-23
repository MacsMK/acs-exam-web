<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionFileService.php
 * Ubicación:      app/Services/QuestionFileService.php
 * Requerimiento:  RF-BAN-012, RF-BAN-013, RNF-SEG-007, RNF-SEG-008
 * Actividad:      ACT-WEB-026
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Guarda la imagen y el PDF opcionales de las preguntas en el
 *                 disco privado, resuelve los archivos de una pregunta nueva,
 *                 editada o versionada, borra del disco solo los archivos que
 *                 ninguna pregunta usa (las versiones y copias comparten el
 *                 archivo) y decide quién puede consultarlos.
 * ============================================================================
 */

namespace App\Services;

use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionFileService
{
    public const DISK = 'local';

    public const DIRECTORY = 'question-files';

    /**
     * Columna de la pregunta para cada tipo de archivo.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'image' => 'image_path',
        'pdf' => 'pdf_path',
    ];

    /**
     * Calcula image_path y pdf_path a partir de los datos validados: un archivo
     * nuevo reemplaza al actual, remove_* lo quita y, si no hay cambio, se
     * conserva el de la pregunta de origen (edición o nueva versión).
     *
     * @param array<string, mixed> $data
     * @return array{image_path: string|null, pdf_path: string|null}
     */
    public function resolvePaths(int $teacherId, array $data, ?Question $source = null): array
    {
        $paths = [];

        foreach (self::COLUMNS as $kind => $column) {
            $upload = $data[$kind] ?? null;

            if ($upload instanceof UploadedFile) {
                $paths[$column] = $this->store($teacherId, $upload, $kind);
            } elseif (! empty($data['remove_'.$kind])) {
                $paths[$column] = null;
            } else {
                $paths[$column] = $source?->{$column};
            }
        }

        return $paths;
    }

    /**
     * Borra del disco los archivos que ya no usa ninguna pregunta, incluidas
     * las eliminadas del banco que siguen en exámenes (RF-BAN-011).
     *
     * @param array<int, string|null> $paths
     */
    public function deleteUnused(array $paths): void
    {
        foreach (array_filter(array_unique($paths)) as $path) {
            $inUse = Question::withTrashed()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where(static fn (Builder $query) => $query->where('image_path', $path)->orWhere('pdf_path', $path))
                ->exists();

            if (! $inUse) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
    }

    /**
     * El docente dueño de la pregunta y los alumnos con un intento en un examen
     * que la incluye pueden consultar sus archivos (RNF-SEG-008).
     */
    public function canView(User $viewer, Question $question): bool
    {
        if ($viewer->role === 'teacher') {
            return $question->teacher_id === $viewer->id;
        }

        if ($viewer->role !== 'student') {
            return false;
        }

        return ExamQuestion::query()
            ->where('question_id', $question->id)
            ->whereHas('exam', static function (Builder $exam) use ($viewer): void {
                $exam->withoutGlobalScope(TenantTeacherScope::class)
                    ->whereHas('studentAttempts', static fn (Builder $attempt) => $attempt->where('student_id', $viewer->id));
            })
            ->exists();
    }

    private function store(int $teacherId, UploadedFile $file, string $kind): string
    {
        $extension = $kind === 'pdf'
            ? 'pdf'
            : (strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg');

        return $file->storeAs(self::DIRECTORY.'/'.$teacherId, Str::uuid()->toString().'.'.$extension, self::DISK);
    }
}
