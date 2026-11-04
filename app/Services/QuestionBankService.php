<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionBankService.php
 * Ubicación:      app/Services/QuestionBankService.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V2 §6.4.1)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.3.0
 * Descripción:    Servicio transaccional del Banco de Reactivos: gestión de las
 *                 4 variantes polimórficas, copy-on-write para reactivos vinculados,
 *                 duplicación, paráfrasis anti-copia, exportación de plantilla CSV
 *                 e importación masiva por lotes.
 * ============================================================================
 */

namespace App\Services;

use App\Models\CodeItem;
use App\Models\ExamQuestion;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\User;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBankService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository
    ) {
    }

    /**
     * Obtiene los reactivos paginados de un docente aplicando filtros dinámicos.
     *
     * @param array<string, mixed>|int $filtersOrPerPage
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacher(User $teacher, array|int $filtersOrPerPage = [], int $perPage = 20): LengthAwarePaginator
    {
        if (is_int($filtersOrPerPage)) {
            $perPage = $filtersOrPerPage;
            $filters = [];
        } else {
            $filters = $filtersOrPerPage;
        }

        return $this->questionRepository->paginateForTeacherWithFilters($teacher->id, $filters, $perPage);
    }

    /**
     * Obtiene la lista de asignaturas/materias únicas registradas por el docente.
     *
     * @return Collection<int, string>
     */
    public function getDistinctSubjectsForTeacher(User $teacher): Collection
    {
        return $this->questionRepository->getDistinctSubjectsForTeacher($teacher->id);
    }

    /**
     * Crea de forma transaccional un reactivo polimórfico y su entidad raíz.
     *
     * @param array<string, mixed> $data
     */
    public function createQuestion(User $teacher, array $data): Question
    {
        return DB::transaction(function () use ($teacher, $data): Question {
            $questionable = $this->createQuestionableVariant((string) $data['question_type'], $data);

            return Question::create([
                'teacher_id' => $teacher->id,
                'subject_tag' => $data['subject_tag'] ?? null,
                'title' => (string) $data['title'],
                'stem' => (string) $data['stem'],
                'question_type' => (string) $data['question_type'],
                'default_points' => $data['default_points'],
                'default_time_seconds' => (int) $data['default_time_seconds'],
                'questionable_type' => $questionable::class,
                'questionable_id' => $questionable->getKey(),
            ]);
        });
    }

    /**
     * Actualiza un reactivo polimórfico.
     * Si no está vinculado a exámenes históricos, se edita in-situ.
     * Si ya está vinculado a uno o más exámenes, ejecuta Copy-On-Write (ADR-003 / RF-BAN-010).
     *
     * @param array<string, mixed> $data
     */
    public function updateQuestion(User $teacher, Question $question, array $data): Question
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para modificar este reactivo.');
        }

        $isLinked = method_exists($question, 'examQuestions')
            ? $question->examQuestions()->exists()
            : ExamQuestion::query()->where('question_id', $question->id)->exists();

        // 1. Edición in-situ si NO está vinculado a ningún examen
        if (! $isLinked) {
            return DB::transaction(function () use ($question, $data): Question {
                $question->update([
                    'title' => (string) $data['title'],
                    'stem' => (string) $data['stem'],
                    'subject_tag' => $data['subject_tag'] ?? null,
                    'default_points' => $data['default_points'],
                    'default_time_seconds' => (int) $data['default_time_seconds'],
                ]);

                $questionable = $question->questionable;

                match ($question->question_type) {
                    'multiple_choice' => $questionable?->update([
                        'options_payload' => collect($data['options'] ?? [])
                            ->values()
                            ->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text])
                            ->all(),
                        'correct_option_index' => (int) $data['correct_option_index'],
                    ]),
                    'matching' => $questionable?->update(
                        $this->buildMatchingPayload($data['concepts'] ?? [], $data['definitions'] ?? [])
                    ),
                    'open' => $questionable?->update([
                        'reference_answer' => (string) $data['reference_answer'],
                        'evaluation_rubric' => $data['evaluation_rubric'] ?? null,
                    ]),
                    'code' => $questionable?->update([
                        'programming_language' => (string) $data['programming_language'],
                        'is_find_error' => (bool) ($data['is_find_error'] ?? false),
                        'starter_code' => $data['starter_code'] ?? null,
                        'reference_solution' => (string) $data['reference_solution'],
                        'instructions' => (string) $data['instructions'],
                    ]),
                    default => null,
                };

                return $question->fresh(['questionable', 'paraphrases']);
            });
        }

        // 2. Copy-On-Write (ADR-003 / RF-BAN-010) si está vinculado a exámenes históricos
        return DB::transaction(function () use ($teacher, $question, $data): Question {
            $newQuestionable = $this->createQuestionableVariant($question->question_type, $data);

            $newQuestion = Question::create([
                'teacher_id' => $teacher->id,
                'subject_tag' => $data['subject_tag'] ?? $question->subject_tag,
                'title' => (string) $data['title'],
                'stem' => (string) $data['stem'],
                'question_type' => $question->question_type,
                'default_points' => $data['default_points'],
                'default_time_seconds' => (int) $data['default_time_seconds'],
                'questionable_type' => $newQuestionable::class,
                'questionable_id' => $newQuestionable->getKey(),
                'parent_question_id' => $question->id,
            ]);

            $question->loadMissing('paraphrases');
            foreach ($question->paraphrases as $paraphrase) {
                $newQuestion->paraphrases()->create([
                    'stem' => $paraphrase->stem,
                ]);
            }

            return $newQuestion->load(['questionable', 'paraphrases']);
        });
    }

    /**
     * Clona un reactivo y su variante polimórfica para el docente.
     */
    public function duplicateQuestion(User $teacher, Question $question): Question
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para duplicar este reactivo.');
        }

        return $this->questionRepository->duplicate($question);
    }

    /**
     * Elimina lógicamente un reactivo del banco.
     */
    public function deleteQuestion(User $teacher, Question $question): bool
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para eliminar este reactivo.');
        }

        return $this->questionRepository->destroySafely($question);
    }

    /**
     * Registra una paráfrasis anti-copia para un reactivo existente (RF-BAN-010).
     */
    public function createParaphrase(Question $question, string $stem): QuestionParaphrase
    {
        return DB::transaction(static fn (): QuestionParaphrase => QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => trim($stem),
        ]));
    }

    /**
     * Elimina una paráfrasis anti-copia de un reactivo.
     */
    public function deleteParaphrase(User $teacher, Question $question, QuestionParaphrase $paraphrase): bool
    {
        if ($question->teacher_id !== $teacher->id || $paraphrase->question_id !== $question->id) {
            abort(403, 'No tiene autorización para eliminar esta paráfrasis.');
        }

        $paraphrase->delete();

        return true;
    }

    /**
     * Genera la plantilla CSV institucional con BOM UTF-8 y 4 filas representativas.
     */
    public function generateCsvTemplate(): string
    {
        $bom = "\xEF\xBB\xBF";
        $headers = [
            'tipo',
            'titulo',
            'enunciado',
            'materia',
            'puntos',
            'tiempo_segundos',
            'opciones_o_definiciones',
            'respuesta_correcta_o_solucion',
            'instrucciones_o_rubrica',
        ];

        $rows = [
            [
                'multiple_choice',
                'Identificador de Clave Primaria en SQL',
                '¿Qué restricción garantiza la unicidad e integridad referencial como identificador primario en una tabla relacional?',
                'Bases de Datos',
                '2.00',
                '60',
                'PRIMARY KEY|FOREIGN KEY|UNIQUE|CHECK',
                '0',
                'Seleccione únicamente una opción.',
            ],
            [
                'matching',
                'Relación de Paradigmas de Programación',
                'Relacione cada paradigma de programación con su postulado o concepto central:',
                'Programación Orientada a Objetos',
                '3.00',
                '120',
                'POO=Encapsulamiento y polimorfismo|Funcional=Funciones puras e inmutabilidad|Reactiva=Flujos de datos asíncronos',
                '',
                'Asocie todos los pares correctamente.',
            ],
            [
                'open',
                'Principio de Responsabilidad Única (SRP)',
                'Explique detalladamente el Principio de Responsabilidad Única de SOLID y por qué favorece el bajo acoplamiento.',
                'Arquitectura de Software',
                '4.00',
                '180',
                '',
                'Una clase o módulo debe tener una y solo una razón para cambiar, encargándose de un ámbito de negocio delimitado.',
                'Mencionar razón de cambio, cohesión y bajo acoplamiento.',
            ],
            [
                'code',
                'Función de Sumatoria de Lista en Python',
                'Escriba una función llamada `sumar_lista(numeros)` que reciba una lista de enteros y retorne la sumatoria total.',
                'Algoritmos y Programación',
                '5.00',
                '240',
                'python',
                "def sumar_lista(numeros):\n    return sum(numeros)",
                'Defina la función exactamente con el nombre indicado y use retorno de valor.',
            ],
        ];

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return $bom;
        }

        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csvData = stream_get_contents($stream);
        fclose($stream);

        return $bom . ($csvData !== false ? $csvData : '');
    }

    /**
     * Importa reactivos masivamente desde un archivo CSV.
     *
     * @return array{imported: int, errors: array<int, string>}
     */
    public function importBatchFromCsv(User $teacher, string $csvContent): array
    {
        $cleanContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
        if ($cleanContent === null || trim($cleanContent) === '') {
            return ['imported' => 0, 'errors' => ['El archivo CSV está vacío o corrupto.']];
        }

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return ['imported' => 0, 'errors' => ['No se pudo abrir el flujo de memoria para el archivo CSV.']];
        }

        fwrite($stream, $cleanContent);
        rewind($stream);

        $imported = 0;
        $errors = [];
        $rowIndex = 0;

        while (($row = fgetcsv($stream)) !== false) {
            $rowIndex++;

            // Omitir cabecera
            if ($rowIndex === 1) {
                continue;
            }

            // Omitir filas en blanco
            if (empty(array_filter($row, static fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            $type = strtolower(trim($row[0] ?? ''));
            $title = trim($row[1] ?? '');
            $stem = trim($row[2] ?? '');
            $subjectTag = trim($row[3] ?? '') ?: null;
            $points = (float) ($row[4] ?? 1.0);
            $timeSeconds = (int) ($row[5] ?? 60);
            $optionsOrDefs = trim($row[6] ?? '');
            $answerOrSolution = trim($row[7] ?? '');
            $instructionsOrRubric = trim($row[8] ?? '');

            if ($title === '' || $stem === '' || ! in_array($type, ['multiple_choice', 'matching', 'open', 'code'], true)) {
                $errors[] = "Fila {$rowIndex}: Tipo de reactivo ('{$type}'), título o enunciado inválidos.";
                continue;
            }

            if ($points <= 0) {
                $points = 1.0;
            }
            if ($timeSeconds <= 0) {
                $timeSeconds = 60;
            }

            $questionData = [
                'question_type' => $type,
                'title' => $title,
                'stem' => $stem,
                'subject_tag' => $subjectTag,
                'default_points' => $points,
                'default_time_seconds' => $timeSeconds,
            ];

            try {
                if ($type === 'multiple_choice') {
                    $rawOptions = array_filter(array_map('trim', explode('|', $optionsOrDefs)), static fn ($opt) => $opt !== '');
                    if (count($rawOptions) < 2) {
                        $errors[] = "Fila {$rowIndex}: Reactivo de opción múltiple debe contener al menos 2 opciones separadas por '|'.";
                        continue;
                    }

                    $correctIndex = is_numeric($answerOrSolution) ? (int) $answerOrSolution : 0;
                    if ($correctIndex < 0 || $correctIndex >= count($rawOptions)) {
                        $correctIndex = 0;
                    }

                    $questionData['options'] = array_values($rawOptions);
                    $questionData['correct_option_index'] = $correctIndex;
                } elseif ($type === 'matching') {
                    $rawPairs = array_filter(array_map('trim', explode('|', $optionsOrDefs)), static fn ($p) => $p !== '');
                    $concepts = [];
                    $definitions = [];

                    foreach ($rawPairs as $pairStr) {
                        $parts = explode('=', $pairStr, 2);
                        if (count($parts) === 2) {
                            $concepts[] = trim($parts[0]);
                            $definitions[] = trim($parts[1]);
                        }
                    }

                    if (count($concepts) < 2) {
                        $errors[] = "Fila {$rowIndex}: Reactivo de relación de columnas debe tener al menos 2 pares con formato 'Concepto=Definición|...'.";
                        continue;
                    }

                    $questionData['concepts'] = $concepts;
                    $questionData['definitions'] = $definitions;
                } elseif ($type === 'open') {
                    if ($answerOrSolution === '') {
                        $errors[] = "Fila {$rowIndex}: Reactivo abierto requiere respuesta de referencia.";
                        continue;
                    }
                    $questionData['reference_answer'] = $answerOrSolution;
                    $questionData['evaluation_rubric'] = $instructionsOrRubric ?: null;
                } elseif ($type === 'code') {
                    $lang = $optionsOrDefs !== '' ? $optionsOrDefs : 'python';
                    if ($answerOrSolution === '') {
                        $errors[] = "Fila {$rowIndex}: Reactivo de código requiere solución de referencia.";
                        continue;
                    }
                    $questionData['programming_language'] = $lang;
                    $questionData['is_find_error'] = false;
                    $questionData['starter_code'] = null;
                    $questionData['reference_solution'] = $answerOrSolution;
                    $questionData['instructions'] = $instructionsOrRubric !== '' ? $instructionsOrRubric : 'Implemente la solución requerida.';
                }

                $this->createQuestion($teacher, $questionData);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Fila {$rowIndex}: Error al crear reactivo: " . $e->getMessage();
            }
        }

        fclose($stream);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * Construye y persiste la variante polimórfica adecuada según el tipo.
     *
     * @param array<string, mixed> $data
     */
    private function createQuestionableVariant(string $questionType, array $data): Model
    {
        return match ($questionType) {
            'multiple_choice' => MultipleChoiceItem::create([
                'options_payload' => collect($data['options'] ?? [])
                    ->values()
                    ->map(fn (string $text, int $index) => ['index' => $index, 'text' => $text])
                    ->all(),
                'correct_option_index' => (int) $data['correct_option_index'],
            ]),
            'matching' => $this->buildMatchingItem($data['concepts'] ?? [], $data['definitions'] ?? []),
            'open' => OpenItem::create([
                'reference_answer' => (string) $data['reference_answer'],
                'evaluation_rubric' => $data['evaluation_rubric'] ?? null,
            ]),
            'code' => CodeItem::create([
                'programming_language' => (string) $data['programming_language'],
                'is_find_error' => (bool) ($data['is_find_error'] ?? false),
                'starter_code' => $data['starter_code'] ?? null,
                'reference_solution' => (string) $data['reference_solution'],
                'instructions' => (string) $data['instructions'],
            ]),
            default => throw ValidationException::withMessages([
                'question_type' => ['La opción seleccionada en tipo de reactivo no es válida'],
            ]),
        };
    }

    /**
     * Construye un MatchingItem a partir de pares concepto/definición con la
     * columna derecha barajada automáticamente.
     *
     * @param array<int, string> $concepts
     * @param array<int, string> $definitions
     */
    private function buildMatchingItem(array $concepts, array $definitions): MatchingItem
    {
        return MatchingItem::create($this->buildMatchingPayload($concepts, $definitions));
    }

    /**
     * Construye las estructuras de columnas izquierda, derecha (barajada) y los pares solución.
     *
     * @param array<int, string> $concepts
     * @param array<int, string> $definitions
     * @return array{left_column_payload: array<int, array{id: string, text: string}>, right_column_payload: array<int, array{id: string, text: string}>, pairs_payload: array<int, array{left_id: string, right_id: string}>}
     */
    private function buildMatchingPayload(array $concepts, array $definitions): array
    {
        $leftItems = [];
        $rightItemsOrdered = [];
        $pairs = [];

        foreach (array_values($concepts) as $index => $concept) {
            $leftId = 'L'.($index + 1);
            $rightId = 'R'.($index + 1);

            $leftItems[] = ['id' => $leftId, 'text' => (string) $concept];
            $rightItemsOrdered[] = ['id' => $rightId, 'text' => (string) ($definitions[$index] ?? '')];
            $pairs[] = ['left_id' => $leftId, 'right_id' => $rightId];
        }

        $rightItemsShuffled = collect($rightItemsOrdered)->shuffle()->values()->all();

        return [
            'left_column_payload' => $leftItems,
            'right_column_payload' => $rightItemsShuffled,
            'pairs_payload' => $pairs,
        ];
    }
}
