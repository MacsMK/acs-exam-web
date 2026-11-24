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
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionFileService
{
    public function __construct(
        private readonly AttemptService $attempts,
        private readonly StudentResultService $studentResults,
    ) {
    }

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
     * Autoriza al docente titular o al alumno que todavía pueda ver el reactivo
     * en su intento o en su revisión publicada (RNF-SEG-008, RF-GRU-011/012/019).
     *
     * @param User $viewer Cuenta que solicita el archivo.
     * @param Question $question Reactivo propietario del archivo.
     * @return bool
     */
    public function canView(User $viewer, Question $question): bool
    {
        if ($viewer->role === 'teacher') {
            return $question->teacher_id === $viewer->id;
        }

        if ($viewer->role !== 'student') {
            return false;
        }

        $questionsByExam = ExamQuestion::query()
            ->where('question_id', $question->id)
            ->get(['id', 'exam_id'])
            ->groupBy('exam_id');

        return StudentAttempt::query()
            ->where('student_id', $viewer->id)
            ->inCurrentGroupsOfStudent($viewer->id)
            ->whereIn('exam_id', $questionsByExam->keys())
            ->with('exam')
            ->get()
            ->contains(function (StudentAttempt $attempt) use ($questionsByExam): bool {
                $sequence = array_map('intval', $attempt->question_sequence_payload ?? []);
                $fileQuestions = $questionsByExam[$attempt->exam_id]->modelKeys();

                if ($this->attempts->isInProgress($attempt)) {
                    return $attempt->free_close_at !== null
                        ? array_intersect($fileQuestions, $sequence) !== []
                        : in_array($sequence[$attempt->current_question_index] ?? null, $fileQuestions, true);
                }

                return $attempt->status === 'submitted'
                    && array_intersect($fileQuestions, $sequence) !== []
                    && $this->studentResults->reviewIsAvailable($attempt);
            });
    }

    private function store(int $teacherId, UploadedFile $file, string $kind): string
    {
        $extension = $kind === 'pdf'
            ? 'pdf'
            : (strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg');

        return $file->storeAs(self::DIRECTORY.'/'.$teacherId, Str::uuid()->toString().'.'.$extension, self::DISK);
    }
}
