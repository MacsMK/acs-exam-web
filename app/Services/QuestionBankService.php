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
 * Versión:        2.0.0
 * Descripción:    Servicio transaccional del Banco de Reactivos: gestión de las
 *                 4 variantes polimórficas con IDs opacos en matching [CRÍTICA-02],
 *                 Copy-On-Write bajo bloqueo pesimista [ALTA-08], cuotas SaaS
 *                 [MEDIA-02], catálogo normalizado acs_subjects, paráfrasis
 *                 anti-copia, captura rápida en lote, plantillas CSV/XLSX
 *                 protegidas contra CWE-1236 e importación dry-run atómica.
 * ============================================================================
 */

namespace App\Services;

use DomainException;
use InvalidArgumentException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\CodeItem;
use App\Models\MatchingItem;
use App\Models\MultipleChoiceItem;
use App\Models\OpenItem;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Contracts\QuestionRepositoryInterface;

class QuestionBankService
{
    public const MAX_IMPORT_ROWS = 250;
    public const MAX_BATCH_CREATE_ITEMS = 30;
    public const MAX_PARAPHRASES_PER_QUESTION = 10;

    /**
     * Cabeceras canónicas obligatorias para plantillas e importación masiva.
     *
     * @var array<int, string>
     */
    public const CANONICAL_IMPORT_HEADERS = [
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

    /**
     * Cabeceras opcionales compatibles (modalidad código encontrar error).
     *
     * @var array<int, string>
     */
    public const OPTIONAL_IMPORT_HEADERS = [
        'es_encuentra_error',
        'codigo_inicial',
    ];

    private const HEADER_COLOR_PRIMARY = '142B4A';
    private const HEADER_COLOR_ACCENT = '16A6A1';
    private const SURFACE_COLOR = 'E9F0F5';

    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly SubscriptionLimitService $subscriptionLimitService,
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
     * Obtiene la lista de materias únicas registradas por identificador de docente.
     *
     * @return Collection<int, string>
     */
    public function getDistinctSubjects(int $teacherId): Collection
    {
        return $this->questionRepository->getDistinctSubjectsForTeacher($teacherId);
    }

    /**
     * Obtiene reactivos específicos de un docente indexados por ID sin consultas en controlador.
     *
     * @param array<int, int|string> $questionIds
     * @return Collection<int, Question>
     */
    public function getQuestionsByIdsForTeacher(int $teacherId, array $questionIds): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $questionIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return collect();
        }

        return Question::query()
            ->with(['questionable', 'subject'])
            ->where('teacher_id', $teacherId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Retorna el tablero de materias con estadísticas de uso, métricas del banco y cuota.
     *
     * @return array{subjects: Collection<int, Subject>, summary: array<string, mixed>, quota: array<string, mixed>}
     */
    public function getSubjectsDashboard(User $teacher): array
    {
        return [
            'subjects' => $this->questionRepository->getSubjectsWithStatsForTeacher($teacher->id),
            'summary' => $this->questionRepository->getBankSummaryMetrics($teacher->id),
            'quota' => $this->subscriptionLimitService->getQuestionQuotaSummary($teacher),
        ];
    }

    /**
     * Sugiere materias similares existentes para evitar duplicados tipográficos.
     *
     * @return Collection<int, Subject>
     */
    public function suggestSubjectsForTeacher(User $teacher, string $candidate): Collection
    {
        return $this->questionRepository->findSimilarSubjects($teacher->id, $candidate);
    }

    /**
     * Fusiona transaccionalmente materias duplicadas del docente bajo un nombre canónico.
     *
     * @param array<int, int> $sourceIds
     */
    public function mergeSubjects(User $teacher, array $sourceIds, string $targetName): Subject
    {
        $cleanTarget = Subject::cleanDisplayName($targetName);
        if ($cleanTarget === null || mb_strlen($cleanTarget, 'UTF-8') < 2) {
            throw ValidationException::withMessages([
                'target_name' => ['El nombre canónico destino debe contener al menos 2 caracteres válidos.'],
            ]);
        }

        return $this->questionRepository->mergeTeacherSubjects(
            $teacher->id,
            array_map('intval', $sourceIds),
            $cleanTarget
        );
    }

    /**
     * Obtiene métricas agregadas del banco para tarjetas KPI sin N+1.
     *
     * @return array<string, mixed>
     */
    public function getBankSummaryMetrics(User $teacher): array
    {
        return $this->questionRepository->getBankSummaryMetrics($teacher->id);
    }

    /**
     * Indica si un reactivo ya está vinculado a exámenes (determina Copy-On-Write).
     */
    public function isQuestionLinkedToExams(Question $question): bool
    {
        return $this->questionRepository->hasExamLinks($question);
    }

    /**
     * Calcula el número de la próxima versión Copy-On-Write dentro del linaje.
     */
    public function getNextVersionNumber(Question $question): int
    {
        $lineageCount = count($this->questionRepository->getLineageQuestionIds($question->id));

        return max(2, $lineageCount + 1);
    }

    /**
     * Prepara las estructuras iniciales para el formulario de edición eliminando bloques @php en Blade.
     *
     * @param array<string, mixed> $oldInput
     * @return array<string, mixed>
     */
    public function buildEditFormState(Question $question, array $oldInput = []): array
    {
        $question->loadMissing(['questionable', 'paraphrases', 'subject']);
        $questionable = $question->questionable;

        $rawOptions = $oldInput['options'] ?? null;
        if (! is_array($rawOptions)) {
            $rawOptions = collect($questionable->options_payload ?? [])
                ->map(static fn (mixed $item): string => is_array($item) ? (string) ($item['text'] ?? '') : (string) $item)
                ->values()
                ->all();
        }
        if ($rawOptions === []) {
            $rawOptions = ['', ''];
        }

        $oldConcepts = $oldInput['concepts'] ?? null;
        $oldDefinitions = $oldInput['definitions'] ?? null;
        if (is_array($oldConcepts) && is_array($oldDefinitions)) {
            $pairs = collect($oldConcepts)
                ->values()
                ->map(static fn (mixed $concept, int $index): array => [
                    'concept' => (string) $concept,
                    'definition' => (string) ($oldDefinitions[$index] ?? ''),
                ])
                ->values()
                ->all();
        } else {
            $leftMap = collect($questionable->left_column_payload ?? [])->keyBy('id');
            $rightMap = collect($questionable->right_column_payload ?? [])->keyBy('id');
            $pairs = collect($questionable->pairs_payload ?? [])
                ->map(static function (mixed $pair) use ($leftMap, $rightMap): array {
                    $left = is_array($pair) ? $leftMap->get($pair['left_id'] ?? '') : null;
                    $right = is_array($pair) ? $rightMap->get($pair['right_id'] ?? '') : null;

                    return [
                        'concept' => is_array($left) ? (string) ($left['text'] ?? '') : (string) ($left ?? ''),
                        'definition' => is_array($right) ? (string) ($right['text'] ?? '') : (string) ($right ?? ''),
                    ];
                })
                ->values()
                ->all();
        }

        if ($pairs === []) {
            $pairs = [
                ['concept' => '', 'definition' => ''],
                ['concept' => '', 'definition' => ''],
            ];
        }

        $isLinked = $this->isQuestionLinkedToExams($question);

        return [
            'initialOptions' => array_values($rawOptions),
            'initialCorrectOption' => (int) ($oldInput['correct_option_index'] ?? ($questionable->correct_option_index ?? 0)),
            'initialPairs' => $pairs,
            'initialIsFindError' => (bool) ($oldInput['is_find_error'] ?? ($questionable->is_find_error ?? false)),
            'nextVersionNumber' => $isLinked ? $this->getNextVersionNumber($question) : max(1, count($this->questionRepository->getLineageQuestionIds($question->id))),
            'isLinkedToExam' => $isLinked,
        ];
    }

    /**
     * Crea de forma transaccional un reactivo polimórfico y su entidad raíz.
     *
     * @param array<string, mixed> $data
     */
    public function createQuestion(User $teacher, array $data): Question
    {
        return DB::transaction(function () use ($teacher, $data): Question {
            $this->subscriptionLimitService->ensureCanCreateQuestionsLocked($teacher, 1);

            return $this->persistQuestionWithVariant($teacher, $data);
        });
    }

    /**
     * Crea de forma atómica un lote de reactivos capturados desde la vista rápida.
     *
     * @param array<int, array<string, mixed>> $items
     * @return Collection<int, Question>
     */
    public function createQuestionBatch(User $teacher, array $items): Collection
    {
        $items = array_values($items);
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['Debes capturar al menos un reactivo en el lote.'],
            ]);
        }

        if (count($items) > self::MAX_BATCH_CREATE_ITEMS) {
            throw ValidationException::withMessages([
                'items' => [sprintf('El lote admite máximo %d reactivos por envío.', self::MAX_BATCH_CREATE_ITEMS)],
            ]);
        }

        return DB::transaction(function () use ($teacher, $items): Collection {
            $this->subscriptionLimitService->ensureCanCreateQuestionsLocked($teacher, count($items));

            $created = collect();
            foreach ($items as $itemData) {
                $created->push($this->persistQuestionWithVariant($teacher, $itemData));
            }

            return $created;
        });
    }

    /**
     * Actualiza un reactivo polimórfico bajo bloqueo pesimista [ALTA-08].
     * Si está vinculado a exámenes históricos ejecuta Copy-On-Write verificando cuota SaaS [MEDIA-02].
     *
     * @param array<string, mixed> $data
     * @return array{question: Question, version_created: bool, version_number: int}
     */
    public function updateQuestion(User $teacher, Question $question, array $data): array
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para modificar este reactivo.');
        }

        return DB::transaction(function () use ($teacher, $question, $data): array {
            /** @var Question $lockedQuestion */
            $lockedQuestion = Question::query()
                ->whereKey($question->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedQuestion->teacher_id !== $teacher->id) {
                abort(403, 'No tiene autorización para modificar este reactivo.');
            }

            $isLinked = $this->questionRepository->hasExamLinks($lockedQuestion);

            $rawSubject = array_key_exists('subject_tag', $data)
                ? ($data['subject_tag'] !== null ? (string) $data['subject_tag'] : null)
                : $lockedQuestion->subject_tag;
            $subject = $this->questionRepository->resolveOrCreateSubject($teacher->id, $rawSubject);

            // 1. Edición in-situ si NO está vinculado a ningún examen
            if (! $isLinked) {
                $lockedQuestion->update([
                    'title' => (string) $data['title'],
                    'stem' => (string) $data['stem'],
                    'subject_id' => $subject?->id,
                    'subject_tag' => $subject?->name,
                    'default_points' => $data['default_points'],
                    'default_time_seconds' => (int) $data['default_time_seconds'],
                ]);

                $lockedQuestion->loadMissing('questionable');
                $questionable = $lockedQuestion->questionable;

                match ($lockedQuestion->question_type) {
                    'multiple_choice' => $questionable?->update([
                        'options_payload' => collect($data['options'] ?? [])
                            ->values()
                            ->map(static fn (string $text, int $index): array => ['index' => $index, 'text' => $text])
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
                        'instructions' => (string) ($data['instructions'] ?? ''),
                    ]),
                    default => null,
                };

                $fresh = $lockedQuestion->fresh(['questionable', 'paraphrases', 'subject']);
                $lineageCount = max(1, count($this->questionRepository->getLineageQuestionIds($lockedQuestion->id)));

                return [
                    'question' => $fresh,
                    'version_created' => false,
                    'version_number' => $lineageCount,
                ];
            }

            // 2. Copy-On-Write (ADR-003 / RF-BAN-010) con validación de cuota bajo lock [MEDIA-02]
            $this->subscriptionLimitService->ensureCanCreateQuestionsLocked($teacher, 1);

            $newQuestionable = $this->createQuestionableVariant($lockedQuestion->question_type, $data);

            $newQuestion = $this->questionRepository->createWithVariant(
                $teacher->id,
                [
                    'title' => (string) $data['title'],
                    'stem' => (string) $data['stem'],
                    'question_type' => $lockedQuestion->question_type,
                    'subject_id' => $subject?->id,
                    'subject_tag' => $subject?->name,
                    'default_points' => $data['default_points'],
                    'default_time_seconds' => (int) $data['default_time_seconds'],
                    'parent_question_id' => $lockedQuestion->id,
                ],
                $newQuestionable
            );

            $lockedQuestion->loadMissing('paraphrases');
            $normalizedNewStem = $this->normalizeComparableText((string) $newQuestion->stem);
            foreach ($lockedQuestion->paraphrases as $paraphrase) {
                $cleanStem = Subject::cleanDisplayName((string) $paraphrase->stem);
                if ($cleanStem === null || $this->normalizeComparableText($cleanStem) === $normalizedNewStem) {
                    continue;
                }
                $this->questionRepository->storeParaphrase($newQuestion, $cleanStem);
            }

            $versionNumber = max(2, count($this->questionRepository->getLineageQuestionIds($newQuestion->id)));

            return [
                'question' => $newQuestion->load(['questionable', 'paraphrases', 'subject']),
                'version_created' => true,
                'version_number' => $versionNumber,
            ];
        });
    }

    /**
     * Clona un reactivo y su variante polimórfica validando cuota SaaS bajo lock [MEDIA-02].
     */
    public function duplicateQuestion(User $teacher, Question $question): Question
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para duplicar este reactivo.');
        }

        return DB::transaction(function () use ($teacher, $question): Question {
            $this->subscriptionLimitService->ensureCanCreateQuestionsLocked($teacher, 1);

            return $this->questionRepository->duplicate($question);
        });
    }

    /**
     * Elimina lógicamente un reactivo si no participa en exámenes publicados o con intentos.
     *
     * @throws DomainException
     */
    public function deleteQuestion(User $teacher, Question $question): bool
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para eliminar este reactivo.');
        }

        if ($this->questionRepository->hasActiveOrPublishedExamLinks($question)) {
            throw new DomainException(
                'No es posible eliminar este reactivo porque forma parte de un examen publicado o con intentos de alumnos. Puedes duplicarlo o editarlo para crear una nueva versión.'
            );
        }

        return $this->questionRepository->destroySafely($question);
    }

    /**
     * Registra una paráfrasis anti-copia para un reactivo existente (RF-BAN-010).
     */
    public function createParaphrase(User $teacher, Question $question, string $stem): QuestionParaphrase
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para agregar paráfrasis a este reactivo.');
        }

        $cleanStem = Subject::cleanDisplayName($stem);
        if ($cleanStem === null || mb_strlen($cleanStem, 'UTF-8') < 3 || mb_strlen($cleanStem, 'UTF-8') > 500) {
            throw ValidationException::withMessages([
                'stem' => ['La paráfrasis debe tener entre 3 y 500 caracteres válidos.'],
            ]);
        }

        return DB::transaction(function () use ($question, $cleanStem): QuestionParaphrase {
            /** @var Question $lockedQuestion */
            $lockedQuestion = Question::query()
                ->whereKey($question->id)
                ->lockForUpdate()
                ->firstOrFail();

            $candidateNormalized = $this->normalizeComparableText($cleanStem);
            if ($candidateNormalized === $this->normalizeComparableText((string) $lockedQuestion->stem)) {
                throw ValidationException::withMessages([
                    'stem' => ['La paráfrasis no puede ser equivalente al enunciado principal del reactivo.'],
                ]);
            }

            $existing = $lockedQuestion->paraphrases()->get();
            if ($existing->count() >= self::MAX_PARAPHRASES_PER_QUESTION) {
                throw ValidationException::withMessages([
                    'stem' => [sprintf('Has alcanzado el límite máximo de %d paráfrasis por reactivo.', self::MAX_PARAPHRASES_PER_QUESTION)],
                ]);
            }

            foreach ($existing as $paraphrase) {
                if ($this->normalizeComparableText((string) $paraphrase->stem) === $candidateNormalized) {
                    throw ValidationException::withMessages([
                        'stem' => ['Ya existe una paráfrasis equivalente registrada para este reactivo.'],
                    ]);
                }
            }

            return $this->questionRepository->storeParaphrase($lockedQuestion, $cleanStem);
        });
    }

    /**
     * Elimina una paráfrasis anti-copia validando propiedad y pertenencia al reactivo [MEDIA-02].
     *
     * @throws DomainException
     */
    public function deleteParaphrase(User $teacher, Question $question, QuestionParaphrase|int $paraphrase): bool
    {
        if ($question->teacher_id !== $teacher->id) {
            abort(403, 'No tiene autorización para eliminar esta paráfrasis.');
        }

        $paraphraseId = $paraphrase instanceof QuestionParaphrase ? (int) $paraphrase->id : $paraphrase;
        $target = $this->questionRepository->findParaphraseForQuestion($question, $paraphraseId);

        if ($target === null) {
            abort(404, 'La paráfrasis solicitada no pertenece a este reactivo.');
        }

        if ($this->questionRepository->hasActiveOrPublishedExamLinks($question)) {
            throw new DomainException(
                'No es posible eliminar paráfrasis de un reactivo que ya participa en un examen publicado o con intentos registrados.'
            );
        }

        return $this->questionRepository->deleteParaphrase($target);
    }

    /**
     * Genera la plantilla CSV institucional con BOM UTF-8 y 5 filas representativas protegidas contra CWE-1236.
     */
    public function generateCsvTemplate(): string
    {
        $bom = "\xEF\xBB\xBF";
        $headers = array_merge(self::CANONICAL_IMPORT_HEADERS, self::OPTIONAL_IMPORT_HEADERS);
        $rows = $this->templateExampleRows();

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return $bom;
        }

        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(fn (string $cell): string => $this->sanitizeSpreadsheetCellForExport($cell), $row)
            );
        }

        rewind($stream);
        $csvData = stream_get_contents($stream);
        fclose($stream);

        return $bom . ($csvData !== false ? $csvData : '');
    }

    /**
     * Genera la plantilla institucional en formato Excel (.xlsx) con instrucciones y ejemplos.
     */
    public function generateExcelTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla Reactivos');

        $headers = array_merge(self::CANONICAL_IMPORT_HEADERS, self::OPTIONAL_IMPORT_HEADERS);
        $columnLetters = range('A', 'K');

        foreach ($headers as $index => $header) {
            $col = $columnLetters[$index];
            $sheet->setCellValueExplicit("{$col}1", $header, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        foreach ($this->templateExampleRows() as $rowOffset => $rowValues) {
            $excelRow = $rowOffset + 2;
            foreach ($rowValues as $colIndex => $cellValue) {
                $col = $columnLetters[$colIndex];
                $sheet->setCellValueExplicit(
                    "{$col}{$excelRow}",
                    $this->sanitizeSpreadsheetCellForExport((string) $cellValue),
                    DataType::TYPE_STRING
                );
            }
        }

        $lastDataRow = count($this->templateExampleRows()) + 1;
        $sheet->getStyle("A2:K{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        foreach ($columnLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Segunda hoja con guía rápida de llenado
        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Instrucciones');
        $guideRows = [
            ['Campo / Columna', 'Regla Obligatoria', 'Ejemplo / Notas'],
            ['tipo', 'multiple_choice | matching | open | code', 'Exactamente en minúsculas'],
            ['titulo', 'Obligatorio, máximo 150 caracteres', 'Nombre corto para identificar en el banco'],
            ['enunciado', 'Obligatorio, entre 3 y 5000 caracteres', 'Pregunta mostrada al estudiante'],
            ['materia', 'Opcional, máximo 100 caracteres', 'Se normaliza automáticamente en acs_subjects'],
            ['puntos', 'Numérico > 0 y <= 100 (máx. 2 decimales)', 'Ej. 2.50'],
            ['tiempo_segundos', 'Entero entre 5 y 3600 segundos', 'Ej. 60'],
            ['opciones_o_definiciones', 'MC: Op1|Op2 | Matching: C1=D1|C2=D2 | Code: lenguaje', 'Mínimo 2 elementos para MC/Matching; máximo 6/8'],
            ['respuesta_correcta_o_solucion', 'MC: índice 0-based | Open: referencia | Code: solución', 'En matching se deja vacío'],
            ['instrucciones_o_rubrica', 'Open: rúbrica | Code/MC/Matching: instrucciones', 'Opcional (máx. 5000/10000 caracteres)'],
            ['es_encuentra_error', 'Opcional (solo código): 0 o 1', 'Si es 1, codigo_inicial es obligatorio'],
            ['codigo_inicial', 'Obligatorio cuando es_encuentra_error = 1', 'Código con defecto mostrado al estudiante'],
        ];

        foreach ($guideRows as $rowIdx => $guideRow) {
            $num = $rowIdx + 1;
            $guideSheet->setCellValueExplicit("A{$num}", $guideRow[0], DataType::TYPE_STRING);
            $guideSheet->setCellValueExplicit("B{$num}", $guideRow[1], DataType::TYPE_STRING);
            $guideSheet->setCellValueExplicit("C{$num}", $guideRow[2], DataType::TYPE_STRING);
        }

        $guideSheet->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_COLOR_ACCENT]],
        ]);
        $guideSheet->getStyle('A2:C12')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SURFACE_COLOR]],
        ]);
        foreach (['A', 'B', 'C'] as $col) {
            $guideSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'acode_xlsx_');
        if ($tmpPath === false) {
            throw new DomainException('No se pudo preparar el archivo temporal de plantilla Excel.');
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmpPath);
            $binary = file_get_contents($tmpPath);

            return $binary !== false ? $binary : '';
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Analiza un archivo CSV/TXT/XLSX en modo Dry-Run sin insertar reactivos en base de datos.
     *
     * @return array{
     *     total_rows: int,
     *     valid_count: int,
     *     error_count: int,
     *     rows: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     quota: array<string, mixed>,
     *     can_commit: bool,
     *     subject_warnings: array<int, array{input: string, suggestions: array<int, string>}>
     * }
     */
    public function previewBatchImport(User $teacher, UploadedFile $file): array
    {
        $parsed = $this->parseAndValidateUploadedFile($teacher, $file);
        $quota = $this->subscriptionLimitService->getQuestionQuotaSummary($teacher);
        $validCount = count($parsed['valid_rows']);
        $hasQuota = $quota['is_unlimited'] || ($quota['remaining'] !== null && $validCount <= (int) $quota['remaining']);

        return [
            'total_rows' => $parsed['total_rows'],
            'valid_count' => $validCount,
            'error_count' => count($parsed['errors']),
            'rows' => $parsed['preview_rows'],
            'errors' => $parsed['errors'],
            'quota' => $quota,
            'can_commit' => $validCount > 0 && $parsed['errors'] === [] && $hasQuota,
            'subject_warnings' => $parsed['subject_warnings'],
        ];
    }

    /**
     * Importa atómicamente todos los reactivos de un archivo CSV/TXT/XLSX dentro de una transacción.
     * Cero inserciones parciales ante errores de validación y propagación directa de cuota SaaS.
     *
     * @return array{imported: int, errors: array<int, string>}
     *
     * @throws SubscriptionLimitExceededException
     */
    public function commitBatchImport(User $teacher, UploadedFile $file): array
    {
        $parsed = $this->parseAndValidateUploadedFile($teacher, $file);

        return $this->commitParsedRows($teacher, $parsed['valid_rows'], $parsed['errors']);
    }

    /**
     * Importa reactivos masivamente desde contenido CSV en memoria preservando compatibilidad.
     *
     * @return array{imported: int, errors: array<int, string>}
     *
     * @throws SubscriptionLimitExceededException
     */
    public function importBatchFromCsv(User $teacher, string $csvContent): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'acode_csv_');
        if ($tmpPath === false) {
            return ['imported' => 0, 'errors' => ['No se pudo preparar el archivo temporal CSV.']];
        }

        try {
            file_put_contents($tmpPath, $csvContent);
            $parsed = $this->parseAndValidateFileAtPath($teacher, $tmpPath, 'csv');

            return $this->commitParsedRows($teacher, $parsed['valid_rows'], $parsed['errors']);
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Protege valores exportados a hojas de cálculo contra inyección de fórmulas (CWE-1236).
     */
    public function sanitizeSpreadsheetCellForExport(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistQuestionWithVariant(User $teacher, array $data): Question
    {
        $subject = $this->questionRepository->resolveOrCreateSubject(
            $teacher->id,
            isset($data['subject_tag']) ? (string) $data['subject_tag'] : null
        );

        $questionable = $this->createQuestionableVariant((string) $data['question_type'], $data);

        $question = $this->questionRepository->createWithVariant(
            $teacher->id,
            [
                'title' => (string) $data['title'],
                'stem' => (string) $data['stem'],
                'question_type' => (string) $data['question_type'],
                'subject_id' => $subject?->id,
                'subject_tag' => $subject?->name,
                'default_points' => $data['default_points'],
                'default_time_seconds' => (int) $data['default_time_seconds'],
            ],
            $questionable
        );

        $initialParaphrases = $data['initial_paraphrases'] ?? null;
        if (is_array($initialParaphrases) && $initialParaphrases !== []) {
            $seen = [$this->normalizeComparableText((string) $question->stem) => true];
            $insertedAny = false;

            foreach (array_slice($initialParaphrases, 0, 3) as $rawParaphrase) {
                if (! is_string($rawParaphrase)) {
                    continue;
                }

                $clean = Subject::cleanDisplayName($rawParaphrase);
                if ($clean === null || mb_strlen($clean, 'UTF-8') < 3 || mb_strlen($clean, 'UTF-8') > 500) {
                    continue;
                }

                $normalized = $this->normalizeComparableText($clean);
                if (isset($seen[$normalized])) {
                    continue;
                }

                $seen[$normalized] = true;
                $this->questionRepository->storeParaphrase($question, $clean);
                $insertedAny = true;
            }

            if ($insertedAny) {
                $question->load('paraphrases');
            }
        }

        return $question;
    }

    /**
     * Ejecuta la inserción atómica de filas ya validadas con verificación de cuota bajo lock.
     *
     * @param array<int, array<string, mixed>> $validRows
     * @param array<int, string> $errors
     * @return array{imported: int, errors: array<int, string>}
     *
     * @throws SubscriptionLimitExceededException
     */
    private function commitParsedRows(User $teacher, array $validRows, array $errors): array
    {
        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        if ($validRows === []) {
            return ['imported' => 0, 'errors' => ['No se encontraron filas de datos válidas en el archivo.']];
        }

        try {
            $imported = DB::transaction(function () use ($teacher, $validRows): int {
                $this->subscriptionLimitService->ensureCanCreateQuestionsLocked($teacher, count($validRows));

                $count = 0;
                foreach ($validRows as $questionData) {
                    $this->persistQuestionWithVariant($teacher, $questionData);
                    $count++;
                }

                return $count;
            });

            return ['imported' => $imported, 'errors' => []];
        } catch (SubscriptionLimitExceededException $e) {
            throw $e;
        } catch (Throwable) {
            return [
                'imported' => 0,
                'errors' => ['Ocurrió un error interno al guardar el lote de reactivos. Ningún registro fue insertado.'],
            ];
        }
    }

    /**
     * @return array{
     *     total_rows: int,
     *     valid_rows: array<int, array<string, mixed>>,
     *     preview_rows: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     subject_warnings: array<int, array{input: string, suggestions: array<int, string>}>
     * }
     */
    private function parseAndValidateUploadedFile(User $teacher, UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            return [
                'total_rows' => 0,
                'valid_rows' => [],
                'preview_rows' => [],
                'errors' => ['No fue posible leer el archivo cargado desde el servidor.'],
                'subject_warnings' => [],
            ];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === '') {
            $mime = (string) $file->getMimeType();
            $extension = str_contains($mime, 'spreadsheetml') ? 'xlsx' : 'csv';
        }

        return $this->parseAndValidateFileAtPath($teacher, $realPath, $extension);
    }

    /**
     * @return array{
     *     total_rows: int,
     *     valid_rows: array<int, array<string, mixed>>,
     *     preview_rows: array<int, array<string, mixed>>,
     *     errors: array<int, string>,
     *     subject_warnings: array<int, array{input: string, suggestions: array<int, string>}>
     * }
     */
    private function parseAndValidateFileAtPath(User $teacher, string $path, string $extension): array
    {
        $errors = [];
        $rawRows = $extension === 'xlsx'
            ? $this->readXlsxRows($path, $errors)
            : $this->readCsvRows($path, $errors);

        if ($errors !== []) {
            return [
                'total_rows' => 0,
                'valid_rows' => [],
                'preview_rows' => [],
                'errors' => $errors,
                'subject_warnings' => [],
            ];
        }

        if ($rawRows === []) {
            return [
                'total_rows' => 0,
                'valid_rows' => [],
                'preview_rows' => [],
                'errors' => ['El archivo está vacío o no contiene cabecera válida.'],
                'subject_warnings' => [],
            ];
        }

        $headerRow = array_shift($rawRows);
        $headerCheck = $this->validateHeaderRow($headerRow);
        if ($headerCheck !== null) {
            return [
                'total_rows' => 0,
                'valid_rows' => [],
                'preview_rows' => [],
                'errors' => [$headerCheck],
                'subject_warnings' => [],
            ];
        }

        $validRows = [];
        $previewRows = [];
        $subjectCandidates = [];
        $totalRows = 0;

        foreach ($rawRows as $entry) {
            $lineNumber = $entry['line'];
            $cells = $entry['cells'];

            $totalRows++;
            $validation = $this->validateImportDataRow($lineNumber, $cells);

            if ($validation['errors'] !== []) {
                foreach ($validation['errors'] as $msg) {
                    $errors[] = $msg;
                }
                $previewRows[] = [
                    'row' => $lineNumber,
                    'status' => 'error',
                    'question_type' => $cells[0] ?? '',
                    'title' => $cells[1] ?? '',
                    'subject_tag' => $cells[3] ?? '',
                    'errors' => $validation['errors'],
                ];
                continue;
            }

            $questionData = $validation['data'];
            $validRows[] = $questionData;

            if (! empty($questionData['subject_tag'])) {
                $subjectCandidates[(string) $questionData['subject_tag']] = true;
            }

            $previewRows[] = [
                'row' => $lineNumber,
                'status' => 'valid',
                'question_type' => $questionData['question_type'],
                'title' => $questionData['title'],
                'subject_tag' => $questionData['subject_tag'],
                'default_points' => $questionData['default_points'],
                'default_time_seconds' => $questionData['default_time_seconds'],
                'errors' => [],
            ];
        }

        if ($totalRows === 0) {
            $errors[] = 'El archivo no contiene filas de datos después de la cabecera.';
        }

        $subjectWarnings = [];
        foreach (array_keys($subjectCandidates) as $candidateSubject) {
            $similar = $this->questionRepository->findSimilarSubjects($teacher->id, $candidateSubject);
            $exactNormalized = Subject::normalizeName($candidateSubject);
            $suggestions = $similar
                ->filter(static fn (Subject $s): bool => $s->normalized_name !== $exactNormalized)
                ->pluck('name')
                ->values()
                ->all();

            if ($suggestions !== []) {
                $subjectWarnings[] = [
                    'input' => $candidateSubject,
                    'suggestions' => $suggestions,
                ];
            }
        }

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'preview_rows' => $previewRows,
            'errors' => $errors,
            'subject_warnings' => $subjectWarnings,
        ];
    }

    /**
     * Lee filas desde CSV/TXT en streaming controlando UTF-8, delimitador , o ; y límite de 250 filas.
     *
     * @param array<int, string> $errors
     * @return array<int, array{line: int, cells: array<int, string>}|array<int, string>>
     */
    private function readCsvRows(string $path, array &$errors): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $errors[] = 'No se pudo abrir el archivo CSV para lectura.';

            return [];
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false || trim(preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? '') === '') {
                $errors[] = 'El archivo CSV está vacío o corrupto.';

                return [];
            }

            if (! mb_check_encoding($firstLine, 'UTF-8')) {
                $errors[] = 'La cabecera del archivo CSV no posee codificación UTF-8 válida.';

                return [];
            }

            $cleanHeaderLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
            $delimiter = substr_count($cleanHeaderLine, ';') > substr_count($cleanHeaderLine, ',') ? ';' : ',';

            rewind($handle);
            $rows = [];
            $lineIndex = 0;
            $dataRowCount = 0;
            $isFirstRow = true;

            while (($rawCells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $lineIndex++;

                if ($isFirstRow && isset($rawCells[0])) {
                    $rawCells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawCells[0]) ?? (string) $rawCells[0];
                }

                $cells = [];
                foreach ($rawCells as $cell) {
                    $strCell = (string) $cell;
                    if (! mb_check_encoding($strCell, 'UTF-8')) {
                        $errors[] = "Fila {$lineIndex}: Contiene caracteres con codificación inválida (no UTF-8).";

                        return [];
                    }
                    $cells[] = $strCell;
                }

                if ($isFirstRow) {
                    $isFirstRow = false;
                    $rows[] = $cells;
                    continue;
                }

                if ($this->isRowBlank($cells)) {
                    continue;
                }

                $dataRowCount++;
                if ($dataRowCount > self::MAX_IMPORT_ROWS) {
                    $errors[] = sprintf(
                        'El archivo excede el límite máximo de %d filas de datos por importación síncrona.',
                        self::MAX_IMPORT_ROWS
                    );

                    return [];
                }

                $rows[] = [
                    'line' => $lineIndex,
                    'cells' => $cells,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lee filas desde Excel (.xlsx) en modo ReadDataOnly controlando el tope de 250 filas.
     *
     * @param array<int, string> $errors
     * @return array<int, mixed>
     */
    private function readXlsxRows(string $path, array &$errors): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable) {
            $errors[] = 'El archivo Excel (.xlsx) está dañado o no tiene un formato válido.';

            return [];
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];
            $dataRowCount = 0;
            $isFirstRow = true;

            foreach ($sheet->getRowIterator() as $row) {
                $lineIndex = $row->getRowIndex();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $cells = [];
                $colCount = 0;
                foreach ($cellIterator as $cell) {
                    if ($colCount >= 11) {
                        break;
                    }
                    $val = $cell->getValue();
                    $cells[] = $val === null ? '' : (string) $val;
                    $colCount++;
                }

                // Recortar columnas vacías al final preservando índice de las primeras 9-11
                while (count($cells) > 11) {
                    array_pop($cells);
                }

                if ($isFirstRow) {
                    $isFirstRow = false;
                    $rows[] = $cells;
                    continue;
                }

                if ($this->isRowBlank($cells)) {
                    continue;
                }

                $dataRowCount++;
                if ($dataRowCount > self::MAX_IMPORT_ROWS) {
                    $errors[] = sprintf(
                        'El archivo Excel excede el límite máximo de %d filas de datos por importación síncrona.',
                        self::MAX_IMPORT_ROWS
                    );

                    return [];
                }

                $rows[] = [
                    'line' => $lineIndex,
                    'cells' => $cells,
                ];
            }

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @param array<int, string> $headerCells
     */
    private function validateHeaderRow(array $headerCells): ?string
    {
        $normalized = array_map(
            static fn (string $cell): string => mb_strtolower((string) (Subject::cleanDisplayName($cell) ?? ''), 'UTF-8'),
            $headerCells
        );

        while ($normalized !== [] && end($normalized) === '') {
            array_pop($normalized);
        }

        if (count($normalized) < count(self::CANONICAL_IMPORT_HEADERS)) {
            return 'La cabecera del archivo es incompleta. Descarga la plantilla institucional e incluye las 9 columnas canónicas obligatorias.';
        }

        foreach (self::CANONICAL_IMPORT_HEADERS as $index => $expectedHeader) {
            if (($normalized[$index] ?? '') !== $expectedHeader) {
                return sprintf(
                    'La columna %d debe llamarse "%s" (se encontró "%s"). Usa la plantilla institucional.',
                    $index + 1,
                    $expectedHeader,
                    $normalized[$index] ?? ''
                );
            }
        }

        return null;
    }

    /**
     * Valida estrictamente una fila importada sin realizar coerciones silenciosas.
     *
     * @param array<int, string> $cells
     * @return array{data: array<string, mixed>, errors: array<int, string>}
     */
    private function validateImportDataRow(int $lineNumber, array $cells): array
    {
        $rowErrors = [];

        $type = mb_strtolower((string) (Subject::cleanDisplayName($cells[0] ?? null) ?? ''), 'UTF-8');
        $title = Subject::cleanDisplayName($cells[1] ?? null) ?? '';
        $stem = $this->cleanImportMultiline($cells[2] ?? '');
        $subjectTag = Subject::cleanDisplayName($cells[3] ?? null);
        $rawPoints = trim((string) ($cells[4] ?? ''));
        $rawTime = trim((string) ($cells[5] ?? ''));
        $optionsOrDefs = trim((string) ($cells[6] ?? ''));
        $answerOrSolution = (string) ($cells[7] ?? '');
        $instructionsOrRubric = $this->cleanImportMultiline($cells[8] ?? '');
        $rawFindError = mb_strtolower(trim((string) ($cells[9] ?? '')), 'UTF-8');
        $rawStarterCode = (string) ($cells[10] ?? '');

        if (! in_array($type, Question::VALID_TYPES, true)) {
            $rowErrors[] = "Fila {$lineNumber}: Tipo de reactivo '{$type}' no válido (use multiple_choice, matching, open o code).";
        }

        if ($title === '') {
            $rowErrors[] = "Fila {$lineNumber}: El título es obligatorio.";
        } elseif (mb_strlen($title, 'UTF-8') > 150) {
            $rowErrors[] = "Fila {$lineNumber}: El título excede el máximo de 150 caracteres.";
        }

        if ($stem === '' || mb_strlen($stem, 'UTF-8') < 3) {
            $rowErrors[] = "Fila {$lineNumber}: El enunciado es obligatorio y debe tener al menos 3 caracteres.";
        } elseif (mb_strlen($stem, 'UTF-8') > 5000) {
            $rowErrors[] = "Fila {$lineNumber}: El enunciado excede el máximo de 5000 caracteres.";
        }

        if ($subjectTag !== null && mb_strlen($subjectTag, 'UTF-8') > 100) {
            $rowErrors[] = "Fila {$lineNumber}: La materia excede el máximo de 100 caracteres.";
        }

        $points = null;
        if ($rawPoints === '' || ! is_numeric($rawPoints) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $rawPoints)) {
            $rowErrors[] = "Fila {$lineNumber}: La ponderación '{$rawPoints}' debe ser numérica con máximo 2 decimales.";
        } else {
            $points = (float) $rawPoints;
            if ($points < 0.1 || $points > 100.0) {
                $rowErrors[] = "Fila {$lineNumber}: La ponderación '{$rawPoints}' debe estar entre 0.1 y 100 puntos.";
            }
        }

        $timeSeconds = null;
        if ($rawTime === '' || ! preg_match('/^\d+$/', $rawTime)) {
            $rowErrors[] = "Fila {$lineNumber}: El tiempo en segundos '{$rawTime}' debe ser un número entero.";
        } else {
            $timeSeconds = (int) $rawTime;
            if ($timeSeconds < 5 || $timeSeconds > 3600) {
                $rowErrors[] = "Fila {$lineNumber}: El tiempo límite ({$timeSeconds} s) debe estar entre 5 y 3600 segundos.";
            }
        }

        if ($rowErrors !== []) {
            return ['data' => [], 'errors' => $rowErrors];
        }

        $questionData = [
            'question_type' => $type,
            'title' => $title,
            'stem' => $stem,
            'subject_tag' => $subjectTag,
            'default_points' => $points,
            'default_time_seconds' => $timeSeconds,
        ];

        if ($type === 'multiple_choice') {
            $segments = explode('|', $optionsOrDefs);
            $options = [];
            foreach ($segments as $segment) {
                $cleanOpt = Subject::cleanDisplayName($segment);
                if ($cleanOpt === null) {
                    $rowErrors[] = "Fila {$lineNumber}: Opción múltiple contiene alternativas vacías separadas por '|'.";
                    break;
                }
                if (mb_strlen($cleanOpt, 'UTF-8') > 500) {
                    $rowErrors[] = "Fila {$lineNumber}: Alguna alternativa de opción múltiple excede 500 caracteres.";
                    break;
                }
                $options[] = $cleanOpt;
            }

            if ($rowErrors === [] && (count($options) < 2 || count($options) > 6)) {
                $rowErrors[] = "Fila {$lineNumber}: Opción múltiple debe contener entre 2 y 6 alternativas separadas por '|'.";
            }

            if ($rowErrors === [] && count($options) !== count(array_unique($options))) {
                $rowErrors[] = "Fila {$lineNumber}: Las alternativas de opción múltiple no pueden repetirse.";
            }

            $rawCorrectIdx = trim($answerOrSolution);
            if ($rawCorrectIdx === '' || ! preg_match('/^\d+$/', $rawCorrectIdx)) {
                $rowErrors[] = "Fila {$lineNumber}: El índice de respuesta correcta '{$rawCorrectIdx}' debe ser un entero base 0.";
            } elseif ($rowErrors === []) {
                $correctIdx = (int) $rawCorrectIdx;
                if ($correctIdx < 0 || $correctIdx >= count($options)) {
                    $rowErrors[] = "Fila {$lineNumber}: El índice de respuesta correcta ({$correctIdx}) está fuera del rango de opciones (0.." . (count($options) - 1) . ').';
                } else {
                    $questionData['options'] = $options;
                    $questionData['correct_option_index'] = $correctIdx;
                }
            }
        } elseif ($type === 'matching') {
            $segments = explode('|', $optionsOrDefs);
            $concepts = [];
            $definitions = [];

            foreach ($segments as $segment) {
                if (! str_contains($segment, '=')) {
                    $rowErrors[] = "Fila {$lineNumber}: Cada par de relación de columnas debe usar formato 'Concepto=Definición'.";
                    break;
                }
                [$rawConcept, $rawDef] = explode('=', $segment, 2);
                $concept = Subject::cleanDisplayName($rawConcept);
                $definition = Subject::cleanDisplayName($rawDef);

                if ($concept === null || $definition === null) {
                    $rowErrors[] = "Fila {$lineNumber}: Ningún concepto o definición en matching puede quedar vacío.";
                    break;
                }

                if (mb_strlen($concept, 'UTF-8') > 500 || mb_strlen($definition, 'UTF-8') > 500) {
                    $rowErrors[] = "Fila {$lineNumber}: Concepto o definición en matching excede los 500 caracteres.";
                    break;
                }

                $concepts[] = $concept;
                $definitions[] = $definition;
            }

            if ($rowErrors === [] && (count($concepts) < 2 || count($concepts) > 8)) {
                $rowErrors[] = "Fila {$lineNumber}: Relación de columnas debe tener entre 2 y 8 pares.";
            }

            if ($rowErrors === [] && count($concepts) !== count(array_unique($concepts))) {
                $rowErrors[] = "Fila {$lineNumber}: Los conceptos en matching no pueden repetirse.";
            }

            if ($rowErrors === [] && count($definitions) !== count(array_unique($definitions))) {
                $rowErrors[] = "Fila {$lineNumber}: Las definiciones en matching no pueden repetirse.";
            }

            if ($rowErrors === []) {
                $questionData['concepts'] = $concepts;
                $questionData['definitions'] = $definitions;
            }
        } elseif ($type === 'open') {
            $cleanRef = $this->cleanImportMultiline($answerOrSolution);
            if ($cleanRef === '') {
                $rowErrors[] = "Fila {$lineNumber}: Reactivo abierto requiere respuesta de referencia.";
            } elseif (mb_strlen($cleanRef, 'UTF-8') > 10000) {
                $rowErrors[] = "Fila {$lineNumber}: La respuesta de referencia excede 10000 caracteres.";
            } elseif (mb_strlen($instructionsOrRubric, 'UTF-8') > 10000) {
                $rowErrors[] = "Fila {$lineNumber}: La rúbrica de evaluación excede 10000 caracteres.";
            } else {
                $questionData['reference_answer'] = $cleanRef;
                $questionData['evaluation_rubric'] = $instructionsOrRubric !== '' ? $instructionsOrRubric : null;
            }
        } elseif ($type === 'code') {
            $lang = Subject::cleanDisplayName($optionsOrDefs) ?? '';
            if ($lang === '' || mb_strlen($lang, 'UTF-8') > 40) {
                $rowErrors[] = "Fila {$lineNumber}: Reactivo de código requiere lenguaje válido (1 a 40 caracteres) en la columna 7.";
            }

            $cleanSolution = trim($answerOrSolution) === '' ? '' : str_replace(["\r\n", "\r"], "\n", $answerOrSolution);
            if ($cleanSolution === '' || mb_strlen($cleanSolution, 'UTF-8') > 10000) {
                $rowErrors[] = "Fila {$lineNumber}: Reactivo de código requiere solución de referencia (1 a 10000 caracteres).";
            }

            if (mb_strlen($instructionsOrRubric, 'UTF-8') > 5000) {
                $rowErrors[] = "Fila {$lineNumber}: Las instrucciones de código exceden 5000 caracteres.";
            }

            $isFindError = false;
            if ($rawFindError !== '') {
                if (in_array($rawFindError, ['1', 'true', 'si', 'sí'], true)) {
                    $isFindError = true;
                } elseif (! in_array($rawFindError, ['0', 'false', 'no'], true)) {
                    $rowErrors[] = "Fila {$lineNumber}: El valor de 'es_encuentra_error' ('{$rawFindError}') debe ser 0/1 o true/false.";
                }
            }

            $starterCode = null;
            if ($isFindError) {
                $starterCode = trim($rawStarterCode) === '' ? null : str_replace(["\r\n", "\r"], "\n", $rawStarterCode);
                if ($starterCode === null || mb_strlen($starterCode, 'UTF-8') > 10000) {
                    $rowErrors[] = "Fila {$lineNumber}: En modalidad 'es_encuentra_error = 1', la columna 'codigo_inicial' es obligatoria.";
                }
            } elseif (trim($rawStarterCode) !== '') {
                $starterCode = str_replace(["\r\n", "\r"], "\n", $rawStarterCode);
            }

            if ($rowErrors === []) {
                $questionData['programming_language'] = $lang;
                $questionData['is_find_error'] = $isFindError;
                $questionData['starter_code'] = $starterCode;
                $questionData['reference_solution'] = $cleanSolution;
                $questionData['instructions'] = $instructionsOrRubric;
            }
        }

        return ['data' => $questionData, 'errors' => $rowErrors];
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
                    ->map(static fn (string $text, int $index): array => ['index' => $index, 'text' => $text])
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
                'instructions' => (string) ($data['instructions'] ?? ''),
            ]),
            default => throw ValidationException::withMessages([
                'question_type' => ['La opción seleccionada en tipo de reactivo no es válida'],
            ]),
        };
    }

    /**
     * @param array<int, string> $concepts
     * @param array<int, string> $definitions
     */
    private function buildMatchingItem(array $concepts, array $definitions): MatchingItem
    {
        return MatchingItem::create($this->buildMatchingPayload($concepts, $definitions));
    }

    /**
     * Construye columnas con identificadores aleatorios opacos sin correlación numérica [CRÍTICA-02].
     *
     * @param array<int, string> $concepts
     * @param array<int, string> $definitions
     * @return array{left_column_payload: array<int, array{id: string, text: string}>, right_column_payload: array<int, array{id: string, text: string}>, pairs_payload: array<int, array{left_id: string, right_id: string}>}
     */
    private function buildMatchingPayload(array $concepts, array $definitions): array
    {
        $conceptsList = array_values($concepts);
        $definitionsList = array_values($definitions);

        if (count($conceptsList) !== count($definitionsList)) {
            throw new InvalidArgumentException('La cantidad de conceptos y definiciones debe ser exactamente igual.');
        }

        $leftItems = [];
        $rightItemsOrdered = [];
        $pairs = [];
        $usedIds = [];

        foreach ($conceptsList as $index => $concept) {
            do {
                $leftId = 'L_' . bin2hex(random_bytes(5));
            } while (isset($usedIds[$leftId]));
            $usedIds[$leftId] = true;

            do {
                $rightId = 'R_' . bin2hex(random_bytes(5));
            } while (isset($usedIds[$rightId]));
            $usedIds[$rightId] = true;

            $leftItems[] = ['id' => $leftId, 'text' => (string) $concept];
            $rightItemsOrdered[] = ['id' => $rightId, 'text' => (string) $definitionsList[$index]];
            $pairs[] = ['left_id' => $leftId, 'right_id' => $rightId];
        }

        $rightItemsShuffled = collect($rightItemsOrdered)->shuffle()->values()->all();

        return [
            'left_column_payload' => $leftItems,
            'right_column_payload' => $rightItemsShuffled,
            'pairs_payload' => $pairs,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function templateExampleRows(): array
    {
        return [
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
                '0',
                '',
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
                '0',
                '',
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
                '0',
                '',
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
                '0',
                '',
            ],
            [
                'code',
                'Corrección de Condición en Python (Encuentra el Error)',
                'Corrija el error lógico en la condición de mayoría de edad.',
                'Algoritmos y Programación',
                '4.00',
                '180',
                'python',
                "def es_mayor(edad):\n    return edad >= 18",
                'Ajuste el operador relacional para incluir los 18 años.',
                '1',
                "def es_mayor(edad):\n    return edad > 18",
            ],
        ];
    }

    /**
     * @param array<int, string> $cells
     */
    private function isRowBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cleanImportMultiline(string $value): string
    {
        $stripped = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", "\u{2060}", "\u{00AD}"], '', $value);
        $spacesNormalized = str_replace(["\u{00A0}", "\u{2007}", "\u{2009}", "\u{202F}"], ' ', $stripped);
        $eolNormalized = str_replace(["\r\n", "\r"], "\n", $spacesNormalized);

        return trim($eolNormalized);
    }

    private function normalizeComparableText(string $value): string
    {
        $clean = Subject::cleanDisplayName($value) ?? '';

        return mb_strtolower($clean, 'UTF-8');
    }
}
