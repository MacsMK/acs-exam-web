<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentQuestionRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentQuestionRepository.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, MPW-V3 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.1.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio del banco de
 *                 preguntas polimórfico con Eager Loading (with, withCount),
 *                 paginación en SQL, catálogo normalizado de materias, fusión
 *                 transaccional, detección fuzzy de duplicados y linaje COW.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use InvalidArgumentException;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionParaphrase;
use App\Models\Scopes\TenantTeacherScope;
use App\Models\Subject;
use App\Repositories\Contracts\QuestionRepositoryInterface;

/**
 * Repositorio Eloquent para las entidades Question, Subject y sus variantes polimórficas.
 *
 * @package App\Repositories\Eloquent
 */
class EloquentQuestionRepository implements QuestionRepositoryInterface
{
    /**
     * Cota máxima de profundidad/pasos para recorridos de grafos (MPW-V3 §5.2.1).
     */
    private const MAX_GRAPH_STEPS = 50;

    /**
     * Umbral mínimo de similar_text para considerar dos materias posibles duplicados.
     */
    private const FUZZY_SIMILAR_TEXT_THRESHOLD = 78.0;

    /**
     * Umbral mínimo de similitud Levenshtein normalizada para posibles duplicados.
     */
    private const FUZZY_LEVENSHTEIN_THRESHOLD = 60.0;

    /**
     * Umbral mínimo de similitud por palabra significativa en materias multi-palabra.
     */
    private const FUZZY_WORD_THRESHOLD = 65.0;

    /**
     * Palabras vacías españolas excluidas al alinear tokens significativos de materias.
     *
     * @var list<string>
     */
    private const SUBJECT_STOPWORDS = [
        'de',
        'del',
        'la',
        'las',
        'el',
        'los',
        'y',
        'e',
        'en',
        'para',
        'por',
    ];

    /**
     * Tokens de nivel académico cuyo contraste distingue cursos distintos (no typos).
     *
     * @var list<string>
     */
    private const ROMAN_LEVEL_TOKENS = [
        'i',
        'ii',
        'iii',
        'iv',
        'v',
        'vi',
        'vii',
        'viii',
        'ix',
        'x',
    ];

    /**
     * Obtiene los reactivos de un docente paginados en SQL con su variante polimórfica precargada.
     *
     * @param int $teacherId Identificador del docente autor.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacher(int $teacherId, int $perPage = 20): LengthAwarePaginator
    {
        return Question::query()
            ->forTeacher($teacherId)
            ->with(['questionable', 'paraphrases', 'subject'])
            ->withCount(['paraphrases', 'childQuestions', 'examQuestions'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Obtiene los reactivos de un docente con filtros avanzados y búsqueda textual, paginados en SQL.
     *
     * @param int                  $teacherId Identificador del docente autor.
     * @param array<string, mixed> $filters   Filtros de consulta (search, question_type, subject_id, subject_tag, paraphrase_status, sort_by).
     * @param int                  $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function paginateForTeacherWithFilters(
        int $teacherId,
        array $filters,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Question::query()
            ->forTeacher($teacherId)
            ->search($filters['search'] ?? null)
            ->filterBy($filters)
            ->with(['questionable', 'paraphrases', 'subject'])
            ->withCount(['paraphrases', 'childQuestions', 'examQuestions'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Obtiene la colección de asignaturas / materias únicas registradas por el docente.
     *
     * @param int $teacherId Identificador del docente autor.
     * @return Collection<int, string>
     */
    public function getDistinctSubjectsForTeacher(int $teacherId): Collection
    {
        $catalogNames = Subject::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->orderBy('name')
            ->pluck('name');

        $unlinkedTags = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->whereNull('subject_id')
            ->whereNotNull('subject_tag')
            ->where('subject_tag', '!=', '')
            ->distinct()
            ->pluck('subject_tag');

        /** @var array<string, string> $deduplicated */
        $deduplicated = [];

        foreach ($catalogNames as $name) {
            $clean = Subject::cleanDisplayName((string) $name);
            if ($clean === null) {
                continue;
            }

            $normalized = Subject::normalizeName($clean);
            if ($normalized !== '' && ! isset($deduplicated[$normalized])) {
                $deduplicated[$normalized] = $clean;
            }
        }

        foreach ($unlinkedTags as $tag) {
            $clean = Subject::cleanDisplayName((string) $tag);
            if ($clean === null) {
                continue;
            }

            $normalized = Subject::normalizeName($clean);
            if ($normalized !== '' && ! isset($deduplicated[$normalized])) {
                $deduplicated[$normalized] = $clean;
            }
        }

        return collect(array_values($deduplicated))
            ->sort(static fn (string $a, string $b): int => strcasecmp(Subject::normalizeName($a), Subject::normalizeName($b)))
            ->values();
    }

    /**
     * Busca un reactivo por su identificador precargando su variante polimórfica y paráfrasis.
     *
     * @param int $questionId Identificador del reactivo.
     * @return Question|null
     */
    public function findByIdWithVariant(int $questionId): ?Question
    {
        return Question::query()
            ->with(['questionable', 'paraphrases', 'subject'])
            ->withCount(['paraphrases', 'childQuestions', 'examQuestions'])
            ->find($questionId);
    }

    /**
     * Obtiene candidatos aleatorios del banco de un docente para sorteo de apartado (RW-13/14),
     * excluyendo los IDs ya incluidos y todo su linaje Copy-On-Write.
     *
     * @param int             $teacherId     Identificador del docente autor.
     * @param string          $questionType  Tipo de reactivo (multiple_choice, matching, open, code).
     * @param array<int, int> $excludedIds   Identificadores de reactivos ya utilizados en el examen.
     * @param int             $limit         Cantidad solicitada de reactivos.
     * @param string|null     $subjectFilter Nombre de materia opcional para filtrar el sorteo.
     * @param int|null        $subjectId     Identificador de materia normalizada opcional.
     * @return Collection<int, Question>
     */
    public function getRandomCandidatesForSection(
        int $teacherId,
        string $questionType,
        array $excludedIds,
        int $limit,
        ?string $subjectFilter = null,
        ?int $subjectId = null
    ): Collection {
        if ($limit <= 0) {
            return collect();
        }

        $expandedExcluded = $this->expandExcludedLineageIds($excludedIds);

        $query = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->forTeacher($teacherId)
            ->ofType($questionType)
            ->when(
                $expandedExcluded !== [],
                static fn (Builder $builder): Builder => $builder->whereNotIn('id', $expandedExcluded)
            );

        $resolvedSubjectId = ($subjectId !== null && $subjectId > 0) ? $subjectId : null;
        $cleanSubjectFilter = Subject::cleanDisplayName($subjectFilter);

        if ($resolvedSubjectId !== null || $cleanSubjectFilter !== null) {
            $query->forSubject($cleanSubjectFilter, $resolvedSubjectId);
        }

        return $query
            ->with(['questionable', 'subject'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Calcula el inventario de reactivos disponibles del docente agrupado por tipo
     * y materia normalizada, excluyendo los IDs ya usados y sus linajes Copy-On-Write.
     *
     * @param int             $teacherId           Identificador del docente autor.
     * @param array<int, int> $excludedQuestionIds Identificadores de reactivos ya incluidos en el examen.
     * @return array{
     *     total: int,
     *     by_type: array{multiple_choice: int, matching: int, open: int, code: int},
     *     by_subject_id: array<int, int>,
     *     by_subject_tag: array<string, int>,
     *     by_type_and_subject: array<string, int>,
     *     subjects: list<array{subject_id: int|null, name: string, normalized_name: string, total: int}>
     * }
     */
    public function getAvailableInventoryForTeacher(int $teacherId, array $excludedQuestionIds = []): array
    {
        $expandedExcluded = $this->expandExcludedLineageIds($excludedQuestionIds);

        $rows = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->leftJoin('acs_subjects', 'acs_subjects.id', '=', 'acs_questions.subject_id')
            ->where('acs_questions.teacher_id', $teacherId)
            ->when(
                $expandedExcluded !== [],
                static fn (Builder $builder): Builder => $builder->whereNotIn('acs_questions.id', $expandedExcluded)
            )
            ->selectRaw(
                'acs_questions.question_type, '
                . 'acs_questions.subject_id, '
                . 'acs_questions.subject_tag, '
                . 'acs_subjects.name as subject_name, '
                . 'acs_subjects.normalized_name as subject_normalized_name, '
                . 'COUNT(*) as aggregate'
            )
            ->groupBy([
                'acs_questions.question_type',
                'acs_questions.subject_id',
                'acs_questions.subject_tag',
                'acs_subjects.name',
                'acs_subjects.normalized_name',
            ])
            ->get();

        $total = 0;
        $byType = [
            'multiple_choice' => 0,
            'matching' => 0,
            'open' => 0,
            'code' => 0,
        ];
        $bySubjectId = [];
        $bySubjectTag = [];
        $byTypeAndSubject = [];
        $subjectsCatalog = [];

        foreach ($rows as $row) {
            $type = (string) $row->question_type;
            $count = (int) $row->aggregate;
            $subjectId = $row->subject_id !== null ? (int) $row->subject_id : null;
            $displayName = Subject::cleanDisplayName(
                $row->subject_name !== null
                    ? (string) $row->subject_name
                    : ($row->subject_tag !== null ? (string) $row->subject_tag : null)
            );
            $normalizedTag = $row->subject_normalized_name !== null
                ? (string) $row->subject_normalized_name
                : ($displayName !== null ? Subject::normalizeName($displayName) : '');

            $total += $count;

            if (! array_key_exists($type, $byType)) {
                $byType[$type] = 0;
            }
            $byType[$type] += $count;

            if ($subjectId !== null && $subjectId > 0) {
                $bySubjectId[$subjectId] = ($bySubjectId[$subjectId] ?? 0) + $count;
                $idComboKey = "{$type}::id:{$subjectId}";
                $byTypeAndSubject[$idComboKey] = ($byTypeAndSubject[$idComboKey] ?? 0) + $count;
            }

            if ($normalizedTag !== '') {
                $bySubjectTag[$normalizedTag] = ($bySubjectTag[$normalizedTag] ?? 0) + $count;
            }

            $subjectKey = $normalizedTag !== '' ? $normalizedTag : '__none__';
            $comboKey = "{$type}::{$subjectKey}";
            $byTypeAndSubject[$comboKey] = ($byTypeAndSubject[$comboKey] ?? 0) + $count;

            if ($normalizedTag !== '' && $displayName !== null) {
                if (! isset($subjectsCatalog[$normalizedTag])) {
                    $subjectsCatalog[$normalizedTag] = [
                        'subject_id' => $subjectId,
                        'name' => $displayName,
                        'normalized_name' => $normalizedTag,
                        'total' => 0,
                    ];
                } elseif ($subjectsCatalog[$normalizedTag]['subject_id'] === null && $subjectId !== null) {
                    $subjectsCatalog[$normalizedTag]['subject_id'] = $subjectId;
                }

                $subjectsCatalog[$normalizedTag]['total'] += $count;
            }
        }

        uasort($subjectsCatalog, static fn (array $a, array $b): int => strcmp($a['normalized_name'], $b['normalized_name']));

        return [
            'total' => $total,
            'by_type' => $byType,
            'by_subject_id' => $bySubjectId,
            'by_subject_tag' => $bySubjectTag,
            'by_type_and_subject' => $byTypeAndSubject,
            'subjects' => array_values($subjectsCatalog),
        ];
    }

    /**
     * Obtiene reactivos disponibles del banco del docente paginados para selección manual,
     * excluyendo los IDs ya incluidos y todo su linaje Copy-On-Write.
     *
     * @param int                  $teacherId   Identificador del docente autor.
     * @param array<int, int>      $excludedIds Identificadores de reactivos ya utilizados en el examen.
     * @param array<string, mixed> $filters     Filtros opcionales (question_type, subject_id, subject_tag, search, sort_by).
     * @param int                  $perPage     Registros por página en SQL.
     * @return LengthAwarePaginator<Question>
     */
    public function getAvailableCandidatesPaginated(
        int $teacherId,
        array $excludedIds,
        array $filters = [],
        int $perPage = 12
    ): LengthAwarePaginator {
        $expandedExcluded = $this->expandExcludedLineageIds($excludedIds);

        return Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->forTeacher($teacherId)
            ->when(
                $expandedExcluded !== [],
                static fn (Builder $builder): Builder => $builder->whereNotIn('acs_questions.id', $expandedExcluded)
            )
            ->search(isset($filters['search']) ? (string) $filters['search'] : null)
            ->filterBy([
                'question_type' => $filters['question_type'] ?? null,
                'subject_id' => $filters['subject_id'] ?? null,
                'subject_tag' => $filters['subject_tag'] ?? null,
                'sort_by' => $filters['sort_by'] ?? 'latest',
            ])
            ->with(['subject', 'paraphrases'])
            ->withCount(['paraphrases'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Filtra una lista ordenada de IDs candidatos conservando únicamente los reactivos
     * activos del docente que estén libres de colisión Copy-On-Write, preservando el orden.
     *
     * @param int             $teacherId    Identificador del docente autor.
     * @param array<int, int> $candidateIds Identificadores candidatos a validar.
     * @param array<int, int> $excludedIds  Identificadores ya incluidos en el examen.
     * @return array<int, int>
     */
    public function filterAvailableQuestionIds(
        int $teacherId,
        array $candidateIds,
        array $excludedIds = []
    ): array {
        $orderedCandidates = [];
        $seenCandidate = [];

        foreach ($candidateIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0 && ! isset($seenCandidate[$id])) {
                $seenCandidate[$id] = true;
                $orderedCandidates[] = $id;
            }
        }

        if ($orderedCandidates === []) {
            return [];
        }

        $expandedExcluded = $this->expandExcludedLineageIds($excludedIds);

        $validIds = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->forTeacher($teacherId)
            ->whereIn('id', $orderedCandidates)
            ->when(
                $expandedExcluded !== [],
                static fn (Builder $builder): Builder => $builder->whereNotIn('id', $expandedExcluded)
            )
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($validIds === []) {
            return [];
        }

        $validLookup = array_flip($validIds);
        $accepted = [];
        $blockedLineage = [];

        foreach ($orderedCandidates as $candidateId) {
            if (! isset($validLookup[$candidateId]) || isset($blockedLineage[$candidateId])) {
                continue;
            }

            $accepted[] = $candidateId;

            foreach ($this->getLineageQuestionIds($candidateId) as $lineageId) {
                $blockedLineage[$lineageId] = true;
            }
        }

        return $accepted;
    }

    /**
     * Crea un reactivo raíz vinculándolo transaccionalmente con su variante polimórfica.
     *
     * @param int                  $teacherId    Identificador del docente autor.
     * @param array<string, mixed> $questionData Atributos del reactivo raíz.
     * @param Model                $questionable Instancia persistida de la variante polimórfica.
     * @return Question
     */
    public function createWithVariant(int $teacherId, array $questionData, Model $questionable): Question
    {
        return DB::transaction(function () use ($teacherId, $questionData, $questionable): Question {
            $subject = $this->resolveSubjectFromPayload($teacherId, $questionData);

            $question = Question::create([
                'teacher_id' => $teacherId,
                'subject_id' => $subject?->id,
                'subject_tag' => $subject?->name,
                'title' => (string) $questionData['title'],
                'stem' => (string) $questionData['stem'],
                'question_type' => (string) $questionData['question_type'],
                'default_points' => $questionData['default_points'],
                'default_time_seconds' => (int) $questionData['default_time_seconds'],
                'questionable_type' => $questionable::class,
                'questionable_id' => $questionable->getKey(),
                'parent_question_id' => $questionData['parent_question_id'] ?? null,
                'image_path' => $questionData['image_path'] ?? null,
                'pdf_path' => $questionData['pdf_path'] ?? null,
            ]);

            return $question->load(['questionable', 'paraphrases', 'subject']);
        });
    }

    /**
     * Clona un reactivo y su variante polimórfica transaccionalmente.
     *
     * @param Question $question Reactivo canónico a duplicar.
     * @return Question
     */
    public function duplicate(Question $question): Question
    {
        return DB::transaction(function () use ($question): Question {
            $question->loadMissing('questionable');

            $clonedQuestionable = null;
            if ($question->questionable !== null) {
                $clonedQuestionable = $question->questionable->replicate();
                $clonedQuestionable->save();
            }

            $clonedQuestion = $question->replicate([
                'paraphrases_count',
                'child_questions_count',
                'exam_questions_count',
            ]);
            $clonedQuestion->title = Str::limit((string) $question->title, 142, '') . ' (Copia)';
            $clonedQuestion->parent_question_id = null;
            $clonedQuestion->subject_id = $question->subject_id;
            $clonedQuestion->subject_tag = $question->subject_tag;

            if ($clonedQuestionable !== null) {
                $clonedQuestion->questionable_id = $clonedQuestionable->getKey();
                $clonedQuestion->questionable_type = $clonedQuestionable::class;
            }

            $clonedQuestion->save();

            return $clonedQuestion->load(['questionable', 'paraphrases', 'subject']);
        });
    }

    /**
     * Elimina lógicamente un reactivo (Soft Delete).
     *
     * @param Question $question Reactivo a eliminar.
     * @return bool
     */
    public function destroySafely(Question $question): bool
    {
        return (bool) $question->delete();
    }

    /**
     * Registra una nueva paráfrasis anti-copia asociada a un reactivo canónico.
     *
     * @param Question $question Reactivo canónico.
     * @param string   $stem     Redacción alterna de la pregunta.
     * @return QuestionParaphrase
     */
    public function storeParaphrase(Question $question, string $stem): QuestionParaphrase
    {
        return QuestionParaphrase::create([
            'question_id' => $question->id,
            'stem' => trim($stem),
        ]);
    }

    /**
     * Resuelve o crea de manera idempotente la materia normalizada del docente.
     *
     * @param int         $teacherId Identificador del docente propietario.
     * @param string|null $rawName   Nombre sin procesar ingresado por el docente.
     * @return Subject|null
     */
    public function resolveOrCreateSubject(int $teacherId, ?string $rawName): ?Subject
    {
        $cleanName = Subject::cleanDisplayName($rawName);
        if ($cleanName === null) {
            return null;
        }

        $normalized = Subject::normalizeName($cleanName);
        if ($normalized === '') {
            return null;
        }

        $displayName = mb_substr($cleanName, 0, 100, 'UTF-8');
        $normalizedName = mb_substr($normalized, 0, 120, 'UTF-8');

        $existing = Subject::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->where('normalized_name', $normalizedName)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(static fn (): Subject => Subject::create([
                'teacher_id' => $teacherId,
                'name' => $displayName,
                'normalized_name' => $normalizedName,
            ]));
        } catch (UniqueConstraintViolationException) {
            return Subject::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where('teacher_id', $teacherId)
                ->where('normalized_name', $normalizedName)
                ->firstOrFail();
        }
    }

    /**
     * Lista las materias del docente con conteo de reactivos y sugerencias de posibles
     * duplicados por similitud fuzzy.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return Collection<int, Subject>
     */
    public function getSubjectsWithStatsForTeacher(int $teacherId): Collection
    {
        $subjects = Subject::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->withCount('questions')
            ->orderBy('name')
            ->get();

        $total = $subjects->count();

        for ($i = 0; $i < $total; $i++) {
            $current = $subjects->get($i);
            $current->setAttribute('questions_count', (int) $current->questions_count);

            $candidates = [];

            for ($j = 0; $j < $total; $j++) {
                if ($i === $j) {
                    continue;
                }

                $other = $subjects->get($j);
                $similarity = $this->computeSubjectSimilarity(
                    (string) $current->normalized_name,
                    (string) $other->normalized_name
                );

                if ($similarity > 0.0) {
                    $candidates[] = [
                        'id' => (int) $other->id,
                        'name' => (string) $other->name,
                        'normalized_name' => (string) $other->normalized_name,
                        'questions_count' => (int) $other->questions_count,
                        'similarity' => $similarity,
                    ];
                }
            }

            usort($candidates, static function (array $a, array $b): int {
                $byCount = $b['questions_count'] <=> $a['questions_count'];
                if ($byCount !== 0) {
                    return $byCount;
                }

                $bySimilarity = $b['similarity'] <=> $a['similarity'];

                return $bySimilarity !== 0 ? $bySimilarity : ($a['id'] <=> $b['id']);
            });

            $current->setAttribute('similar_to', $candidates[0] ?? null);
            $current->setAttribute('similar_subjects', $candidates);
        }

        return $subjects;
    }

    /**
     * Busca materias del docente con alta similitud léxica frente a un nombre candidato.
     *
     * @param int      $teacherId        Identificador del docente propietario.
     * @param string   $candidateName    Nombre candidato a comparar.
     * @param int|null $excludeSubjectId Identificador de materia a excluir de la búsqueda.
     * @return Collection<int, Subject>
     */
    public function findSimilarSubjects(
        int $teacherId,
        string $candidateName,
        ?int $excludeSubjectId = null
    ): Collection {
        $candidateNormalized = Subject::normalizeName($candidateName);
        if ($candidateNormalized === '') {
            return collect();
        }

        $subjects = Subject::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->when(
                $excludeSubjectId !== null,
                static fn (Builder $query): Builder => $query->where('id', '!=', $excludeSubjectId)
            )
            ->withCount('questions')
            ->orderBy('name')
            ->get();

        return $subjects
            ->map(function (Subject $subject) use ($candidateNormalized): Subject {
                $score = $this->computeSubjectSimilarity($candidateNormalized, (string) $subject->normalized_name);
                $subject->setAttribute('questions_count', (int) $subject->questions_count);
                $subject->setAttribute('similarity', $score);

                return $subject;
            })
            ->filter(static fn (Subject $subject): bool => (float) $subject->getAttribute('similarity') > 0.0)
            ->sort(static function (Subject $a, Subject $b): int {
                $byScore = (float) $b->getAttribute('similarity') <=> (float) $a->getAttribute('similarity');

                return $byScore !== 0
                    ? $byScore
                    : ((int) $b->questions_count <=> (int) $a->questions_count);
            })
            ->values();
    }

    /**
     * Fusiona transaccionalmente múltiples materias del docente hacia una materia destino,
     * reasignando reactivos y apartados de examen y eliminando las materias absorbidas.
     *
     * @param int             $teacherId        Identificador del docente propietario.
     * @param array<int, int> $sourceSubjectIds Identificadores de materias origen a fusionar.
     * @param string          $targetName       Nombre legible destino.
     * @return Subject
     */
    public function mergeTeacherSubjects(int $teacherId, array $sourceSubjectIds, string $targetName): Subject
    {
        $cleanTarget = Subject::cleanDisplayName($targetName);
        if ($cleanTarget === null || Subject::normalizeName($cleanTarget) === '') {
            throw new InvalidArgumentException('El nombre de la materia destino no puede estar vacío.');
        }

        $displayTarget = mb_substr($cleanTarget, 0, 100, 'UTF-8');
        $cleanSourceIds = array_values(array_unique(array_filter(
            array_map('intval', $sourceSubjectIds),
            static fn (int $id): bool => $id > 0
        )));

        return DB::transaction(function () use ($teacherId, $cleanSourceIds, $displayTarget): Subject {
            $targetSubject = $this->resolveOrCreateSubject($teacherId, $displayTarget);
            if ($targetSubject === null) {
                throw new InvalidArgumentException('No fue posible resolver la materia destino.');
            }

            if ($targetSubject->name !== $displayTarget) {
                $targetSubject->update(['name' => $displayTarget]);
            }

            $validSourceIds = $cleanSourceIds === []
                ? []
                : Subject::query()
                    ->withoutGlobalScope(TenantTeacherScope::class)
                    ->where('teacher_id', $teacherId)
                    ->whereIn('id', $cleanSourceIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

            $idsToReassign = array_values(array_unique(array_merge($validSourceIds, [(int) $targetSubject->id])));

            Question::withTrashed()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where('teacher_id', $teacherId)
                ->whereIn('subject_id', $idsToReassign)
                ->update([
                    'subject_id' => $targetSubject->id,
                    'subject_tag' => $targetSubject->name,
                ]);

            ExamSection::query()
                ->whereHas('exam', static function (Builder $examQuery) use ($teacherId): void {
                    $examQuery->withoutGlobalScope(TenantTeacherScope::class)
                        ->where('teacher_id', $teacherId);
                })
                ->whereIn('subject_id', $idsToReassign)
                ->update([
                    'subject_id' => $targetSubject->id,
                    'subject_tag' => $targetSubject->name,
                ]);

            $idsToDelete = array_values(array_diff($validSourceIds, [(int) $targetSubject->id]));
            if ($idsToDelete !== []) {
                Subject::query()
                    ->withoutGlobalScope(TenantTeacherScope::class)
                    ->where('teacher_id', $teacherId)
                    ->whereIn('id', $idsToDelete)
                    ->delete();
            }

            return $targetSubject->fresh()->loadCount('questions');
        });
    }

    /**
     * Calcula los totales agregados del banco de reactivos de un docente en SQL.
     *
     * @param int $teacherId Identificador del docente propietario.
     * @return array{
     *     total_active: int,
     *     with_paraphrases: int,
     *     without_paraphrases: int,
     *     by_type: array{multiple_choice: int, matching: int, open: int, code: int},
     *     subjects_count: int,
     *     duplicate_subject_groups_count: int
     * }
     */
    public function getBankSummaryMetrics(int $teacherId): array
    {
        $typeCounts = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->selectRaw('question_type, COUNT(*) as aggregate')
            ->groupBy('question_type')
            ->pluck('aggregate', 'question_type');

        $byType = [
            'multiple_choice' => (int) ($typeCounts['multiple_choice'] ?? 0),
            'matching' => (int) ($typeCounts['matching'] ?? 0),
            'open' => (int) ($typeCounts['open'] ?? 0),
            'code' => (int) ($typeCounts['code'] ?? 0),
        ];

        $totalActive = array_sum($byType);

        $withParaphrases = Question::query()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->where('teacher_id', $teacherId)
            ->whereHas('paraphrases')
            ->count();

        $subjects = $this->getSubjectsWithStatsForTeacher($teacherId);
        $distinctSubjectsCount = $this->getDistinctSubjectsForTeacher($teacherId)->count();

        return [
            'total_active' => $totalActive,
            'with_paraphrases' => $withParaphrases,
            'without_paraphrases' => max(0, $totalActive - $withParaphrases),
            'by_type' => $byType,
            'subjects_count' => $distinctSubjectsCount,
            'duplicate_subject_groups_count' => $this->countDuplicateSubjectGroups($subjects),
        ];
    }

    /**
     * Indica si el reactivo está vinculado a algún examen en acs_exam_question.
     *
     * @param Question $question Reactivo a consultar.
     * @return bool
     */
    public function hasExamLinks(Question $question): bool
    {
        return $question->examQuestions()->exists();
    }

    /**
     * Indica si el reactivo está vinculado a un examen publicado o que ya tiene intentos registrados.
     *
     * @param Question $question Reactivo a consultar.
     * @return bool
     */
    public function hasActiveOrPublishedExamLinks(Question $question): bool
    {
        return $question->examQuestions()
            ->whereHas('exam', static function (Builder $examQuery): void {
                $examQuery->withoutGlobalScope(TenantTeacherScope::class)
                    ->where(static function (Builder $condition): void {
                        $condition->where('is_published', true)
                            ->orWhereHas('studentAttempts');
                    });
            })
            ->exists();
    }

    /**
     * Obtiene todos los IDs de la familia de versionado Copy-On-Write vinculada al reactivo.
     *
     * @param int $questionId Identificador del reactivo semilla.
     * @return array<int, int>
     */
    public function getLineageQuestionIds(int $questionId): array
    {
        $start = Question::withTrashed()
            ->withoutGlobalScope(TenantTeacherScope::class)
            ->select(['id', 'parent_question_id'])
            ->find($questionId);

        if ($start === null) {
            return [];
        }

        $rootId = (int) $start->id;
        $currentParentId = $start->parent_question_id !== null ? (int) $start->parent_question_id : null;
        $visitedUp = [$rootId => true];
        $attempts = 0;

        while (
            $currentParentId !== null
            && ! isset($visitedUp[$currentParentId])
            && $attempts < self::MAX_GRAPH_STEPS
        ) {
            $attempts++;
            $parent = Question::withTrashed()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->select(['id', 'parent_question_id'])
                ->find($currentParentId);

            if ($parent === null) {
                break;
            }

            $rootId = (int) $parent->id;
            $visitedUp[$rootId] = true;
            $currentParentId = $parent->parent_question_id !== null ? (int) $parent->parent_question_id : null;
        }

        $lineage = [$rootId => true];
        $frontier = [$rootId];
        $attempts = 0;

        while ($frontier !== [] && $attempts < self::MAX_GRAPH_STEPS) {
            $attempts++;
            $childIds = Question::withTrashed()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->whereIn('parent_question_id', $frontier)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $nextFrontier = [];
            foreach ($childIds as $childId) {
                if (! isset($lineage[$childId])) {
                    $lineage[$childId] = true;
                    $nextFrontier[] = $childId;
                }
            }

            $frontier = $nextFrontier;
        }

        return array_keys($lineage);
    }

    /**
     * Busca una paráfrasis específica perteneciente al reactivo indicado.
     *
     * @param Question $question     Reactivo canónico propietario.
     * @param int      $paraphraseId Identificador de la paráfrasis.
     * @return QuestionParaphrase|null
     */
    public function findParaphraseForQuestion(Question $question, int $paraphraseId): ?QuestionParaphrase
    {
        return $question->paraphrases()->whereKey($paraphraseId)->first();
    }

    /**
     * Elimina una paráfrasis anti-copia.
     *
     * @param QuestionParaphrase $paraphrase Paráfrasis a eliminar.
     * @return bool
     */
    public function deleteParaphrase(QuestionParaphrase $paraphrase): bool
    {
        return (bool) $paraphrase->delete();
    }

    /**
     * Resuelve la materia a asociar durante la creación de un reactivo a partir
     * de subject_id explícito o subject_tag textual.
     *
     * @param int                  $teacherId    Identificador del docente propietario.
     * @param array<string, mixed> $questionData Datos del reactivo.
     * @return Subject|null
     */
    private function resolveSubjectFromPayload(int $teacherId, array $questionData): ?Subject
    {
        if (! empty($questionData['subject_id']) && is_numeric($questionData['subject_id'])) {
            $subject = Subject::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->where('teacher_id', $teacherId)
                ->find((int) $questionData['subject_id']);

            if ($subject !== null) {
                return $subject;
            }
        }

        return $this->resolveOrCreateSubject($teacherId, isset($questionData['subject_tag']) ? (string) $questionData['subject_tag'] : null);
    }

    /**
     * Expande la lista de IDs excluidos incorporando las familias Copy-On-Write
     * relacionadas para evitar duplicados semánticos en un mismo examen.
     *
     * @param array<int, int> $excludedIds Identificadores base excluidos.
     * @return array<int, int>
     */
    private function expandExcludedLineageIds(array $excludedIds): array
    {
        $expanded = [];

        foreach ($excludedIds as $rawId) {
            $questionId = (int) $rawId;
            if ($questionId <= 0 || isset($expanded[$questionId])) {
                continue;
            }

            $expanded[$questionId] = true;

            foreach ($this->getLineageQuestionIds($questionId) as $lineageId) {
                $expanded[$lineageId] = true;
            }
        }

        return array_keys($expanded);
    }

    /**
     * Calcula el puntaje de similitud fuzzy entre dos nombres normalizados de materias.
     * Devuelve 0.0 si no superan el umbral razonable o si difieren en nivel numérico/romano.
     *
     * @param string $normalizedA Primera llave normalizada.
     * @param string $normalizedB Segunda llave normalizada.
     * @return float Porcentaje de similitud entre 0.0 y 100.0.
     */
    private function computeSubjectSimilarity(string $normalizedA, string $normalizedB): float
    {
        if ($normalizedA === '' || $normalizedB === '') {
            return 0.0;
        }

        if ($normalizedA === $normalizedB) {
            return 100.0;
        }

        if (strlen($normalizedA) < 4 || strlen($normalizedB) < 4) {
            return 0.0;
        }

        $levelsA = $this->extractLevelTokens($normalizedA);
        $levelsB = $this->extractLevelTokens($normalizedB);
        if (($levelsA !== [] || $levelsB !== []) && $levelsA !== $levelsB) {
            return 0.0;
        }

        $maxLength = max(strlen($normalizedA), strlen($normalizedB));
        $levenshteinDistance = levenshtein($normalizedA, $normalizedB);
        $levenshteinSimilarity = (1.0 - ($levenshteinDistance / $maxLength)) * 100.0;

        $similarPercent = 0.0;
        similar_text($normalizedA, $normalizedB, $similarPercent);

        if (
            $similarPercent < self::FUZZY_SIMILAR_TEXT_THRESHOLD
            || $levenshteinSimilarity < self::FUZZY_LEVENSHTEIN_THRESHOLD
        ) {
            return 0.0;
        }

        $tokensA = $this->extractSignificantTokens($normalizedA);
        $tokensB = $this->extractSignificantTokens($normalizedB);

        if (count($tokensA) === count($tokensB) && count($tokensA) > 1) {
            foreach ($tokensA as $index => $wordA) {
                $wordB = $tokensB[$index];
                $wordPercent = 0.0;
                similar_text($wordA, $wordB, $wordPercent);

                $isPrefixMatch = min(strlen($wordA), strlen($wordB)) >= 4
                    && (str_starts_with($wordA, $wordB) || str_starts_with($wordB, $wordA));

                if ($wordPercent < self::FUZZY_WORD_THRESHOLD && ! $isPrefixMatch) {
                    return 0.0;
                }
            }
        }

        return round(($similarPercent + $levenshteinSimilarity) / 2.0, 1);
    }

    /**
     * Extrae tokens de nivel (números arábigos o numerales romanos I-X) para evitar
     * falsos positivos entre asignaturas seriadas (ej. Física I vs Física II).
     *
     * @param string $normalized Nombre normalizado de la materia.
     * @return array<int, string>
     */
    private function extractLevelTokens(string $normalized): array
    {
        $levels = [];

        foreach (explode(' ', $normalized) as $token) {
            if ($token === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $token) === 1 || in_array($token, self::ROMAN_LEVEL_TOKENS, true)) {
                $levels[] = $token;
            }
        }

        return $levels;
    }

    /**
     * Extrae palabras significativas excluyendo preposiciones y artículos cortos.
     *
     * @param string $normalized Nombre normalizado de la materia.
     * @return array<int, string>
     */
    private function extractSignificantTokens(string $normalized): array
    {
        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::SUBJECT_STOPWORDS, true)
        ));
    }

    /**
     * Cuenta cuántos grupos de materias con posibles duplicados existen mediante
     * búsqueda en anchura acotada sobre componentes conexos.
     *
     * @param Collection<int, Subject> $subjects Colección de materias del docente.
     * @return int
     */
    private function countDuplicateSubjectGroups(Collection $subjects): int
    {
        $total = $subjects->count();
        if ($total < 2) {
            return 0;
        }

        /** @var array<int, bool> $visited */
        $visited = [];
        $groupsCount = 0;

        for ($start = 0; $start < $total; $start++) {
            if (isset($visited[$start])) {
                continue;
            }

            $visited[$start] = true;
            $queue = [$start];
            $componentSize = 0;
            $steps = 0;
            $maxSteps = $total * $total;

            while ($queue !== [] && $steps < $maxSteps) {
                $steps++;
                $currentIndex = array_shift($queue);
                $componentSize++;

                $currentNormalized = (string) $subjects->get($currentIndex)->normalized_name;

                for ($neighbor = 0; $neighbor < $total; $neighbor++) {
                    if (isset($visited[$neighbor])) {
                        continue;
                    }

                    $neighborNormalized = (string) $subjects->get($neighbor)->normalized_name;
                    if ($this->computeSubjectSimilarity($currentNormalized, $neighborNormalized) > 0.0) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            if ($componentSize >= 2) {
                $groupsCount++;
            }
        }

        return $groupsCount;
    }
}
