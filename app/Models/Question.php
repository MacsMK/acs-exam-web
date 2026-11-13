<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        Question.php
 * Ubicación:      app/Models/Question.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, Módulo 5)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_questions (tabla raíz polimórfica
 *                 del banco de preguntas, Módulo 5) con aislamiento por
 *                 docente (MPW-V2 §2.3.4, §6.6), catálogo normalizado de
 *                 materias, SoftDeletes y scopes de búsqueda tolerante.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Scopes\TenantTeacherScope;

/**
 * Reactivo raíz polimórfico del banco de preguntas de un docente.
 *
 * @package App\Models
 */
#[ScopedBy([TenantTeacherScope::class])]
class Question extends Model
{
    use SoftDeletes;

    /**
     * Tipos polimórficos válidos permitidos en el banco de preguntas.
     *
     * @var list<string>
     */
    public const VALID_TYPES = [
        'multiple_choice',
        'matching',
        'open',
        'code',
    ];

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_questions';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'subject_id',
        'subject_tag',
        'title',
        'stem',
        'question_type',
        'default_points',
        'default_time_seconds',
        'questionable_type',
        'questionable_id',
        'parent_question_id',
    ];

    /**
     * Resuelve el enlace de ruta omitiendo únicamente TenantTeacherScope para
     * permitir que la política/controlador emita 403 Forbidden ante intentos de
     * IDOR (MPW-V2 §6.6), preservando SoftDeletingScope (404 en eliminados).
     *
     * @param mixed       $value Valor recibido en la ruta.
     * @param string|null $field Columna de búsqueda alternativa.
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withoutGlobalScope(TenantTeacherScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * Docente autor del reactivo.
     *
     * @return BelongsTo<User, Question>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Materia normalizada a la que pertenece el reactivo.
     *
     * @return BelongsTo<Subject, Question>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Variante específica del reactivo (MultipleChoiceItem, MatchingItem,
     * OpenItem o CodeItem), según question_type.
     *
     * @return MorphTo<Model, Question>
     */
    public function questionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Pregunta original de la que esta es una copia o edición (versionado en el
     * armado, RW-15).
     *
     * @return BelongsTo<Question, Question>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_question_id');
    }

    /**
     * Alias semántico para parent() (retrocompatibilidad).
     *
     * @return BelongsTo<Question, Question>
     */
    public function parentQuestion(): BelongsTo
    {
        return $this->parent();
    }

    /**
     * Preguntas derivadas o ediciones generadas a partir de este reactivo.
     *
     * @return HasMany<Question>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_question_id');
    }

    /**
     * Alias semántico para children() (retrocompatibilidad).
     *
     * @return HasMany<Question>
     */
    public function childQuestions(): HasMany
    {
        return $this->children();
    }

    /**
     * Vínculos de este reactivo con exámenes armados (acs_exam_question).
     *
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'question_id');
    }

    /**
     * Variantes de redacción (anti-copia) de este reactivo canónico en orden determinista.
     *
     * @return HasMany<QuestionParaphrase>
     */
    public function paraphrases(): HasMany
    {
        return $this->hasMany(QuestionParaphrase::class)->orderBy('id');
    }

    /**
     * Scope local de aislamiento multi-docente (MPW-V2 §2.3.4, §6.6).
     *
     * @param Builder<Question> $query     Constructor de la consulta Eloquent.
     * @param int               $teacherId Identificador del docente propietario.
     * @return Builder<Question>
     */
    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where($this->qualifyColumn('teacher_id'), $teacherId);
    }

    /**
     * Scope local para filtrar reactivos por variante polimórfica.
     *
     * @param Builder<Question> $query        Constructor de la consulta Eloquent.
     * @param string            $questionType Tipo de reactivo (multiple_choice, matching, open, code).
     * @return Builder<Question>
     */
    public function scopeOfType(Builder $query, string $questionType): Builder
    {
        return $query->where($this->qualifyColumn('question_type'), $questionType);
    }

    /**
     * Scope local para filtrar por materia de forma tolerante a mayúsculas, acentos y espacios.
     *
     * @param Builder<Question> $query      Constructor de la consulta Eloquent.
     * @param string|null       $subjectTag Nombre o etiqueta de materia a filtrar.
     * @param int|null          $subjectId  Identificador opcional del catálogo acs_subjects.
     * @return Builder<Question>
     */
    public function scopeForSubject(Builder $query, ?string $subjectTag, ?int $subjectId = null): Builder
    {
        $subjectColumn = $this->qualifyColumn('subject_tag');

        if ($subjectId !== null && $subjectId > 0) {
            $subject = Subject::query()
                ->withoutGlobalScope(TenantTeacherScope::class)
                ->select(['id', 'teacher_id', 'name', 'normalized_name'])
                ->find($subjectId);

            if ($subject === null) {
                return $query->where($this->qualifyColumn('subject_id'), $subjectId);
            }

            return $query->where(function (Builder $subQuery) use ($subject, $subjectColumn): void {
                $subQuery->where($this->qualifyColumn('subject_id'), $subject->id)
                    ->orWhere(function (Builder $legacyQuery) use ($subject, $subjectColumn): void {
                        $legacyQuery->whereNull($this->qualifyColumn('subject_id'))
                            ->where($this->qualifyColumn('teacher_id'), $subject->teacher_id)
                            ->where(function (Builder $tagQuery) use ($subject, $subjectColumn): void {
                                $this->applyNormalizedTagEquals($tagQuery, $subjectColumn, (string) $subject->name, (string) $subject->normalized_name);
                            });
                    });
            });
        }

        $cleanSubject = Subject::cleanDisplayName($subjectTag);
        if ($cleanSubject === null) {
            return $query;
        }

        $normalized = Subject::normalizeName($cleanSubject);

        return $query->where(function (Builder $subQuery) use ($cleanSubject, $normalized, $subjectColumn): void {
            $subQuery->where($subjectColumn, $cleanSubject);

            if ($normalized !== '') {
                $subQuery->orWhereHas('subject', static function (Builder $subjectQuery) use ($normalized): void {
                    $subjectQuery->where('normalized_name', $normalized);
                });
            }

            $this->applyNormalizedTagEquals($subQuery, $subjectColumn, $cleanSubject, $normalized);
        });
    }

    /**
     * Scope local para búsqueda textual en título, redacción (stem) y materia (subject_tag / acs_subjects).
     * Emplea ILIKE en PostgreSQL y comparación normalizada sin acentos para materia.
     *
     * @param Builder<Question> $query Constructor de la consulta Eloquent.
     * @param string|null       $term  Término de búsqueda ingresado por el docente.
     * @return Builder<Question>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $cleanTerm = Subject::cleanDisplayName($term);
        if ($cleanTerm === null) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';
        $searchPattern = '%' . $cleanTerm . '%';
        $normalizedTerm = Subject::normalizeName($cleanTerm);
        $subjectColumn = $this->qualifyColumn('subject_tag');

        return $query->where(function (Builder $subQuery) use (
            $driver,
            $likeOperator,
            $searchPattern,
            $normalizedTerm,
            $subjectColumn
        ): void {
            $subQuery->where($this->qualifyColumn('title'), $likeOperator, $searchPattern)
                ->orWhere($this->qualifyColumn('stem'), $likeOperator, $searchPattern)
                ->orWhere($subjectColumn, $likeOperator, $searchPattern);

            if ($normalizedTerm !== '') {
                $normalizedPattern = '%' . $normalizedTerm . '%';

                $subQuery->orWhereHas('subject', static function (Builder $subjectQuery) use ($likeOperator, $normalizedPattern): void {
                    $subjectQuery->where('normalized_name', $likeOperator, $normalizedPattern);
                });

                if ($driver === 'pgsql') {
                    $subQuery->orWhereRaw(
                        $this->normalizedSubjectSqlExpression($subjectColumn) . ' LIKE ?',
                        [$normalizedPattern]
                    );
                }
            }
        });
    }

    /**
     * Scope local para aplicar filtros avanzados y ordenamiento en el banco de reactivos.
     *
     * @param Builder<Question>    $query   Constructor de la consulta Eloquent.
     * @param array<string, mixed> $filters Filtros (question_type, subject_id, subject_tag, paraphrase_status, sort_by).
     * @return Builder<Question>
     */
    public function scopeFilterBy(Builder $query, array $filters): Builder
    {
        if (
            ! empty($filters['question_type']) &&
            in_array($filters['question_type'], self::VALID_TYPES, true)
        ) {
            $query->where($this->qualifyColumn('question_type'), $filters['question_type']);
        }

        $subjectId = isset($filters['subject_id']) && is_numeric($filters['subject_id'])
            ? (int) $filters['subject_id']
            : null;
        $subjectTag = isset($filters['subject_tag']) ? (string) $filters['subject_tag'] : null;

        if (($subjectId !== null && $subjectId > 0) || Subject::cleanDisplayName($subjectTag) !== null) {
            $query->forSubject($subjectTag, $subjectId);
        }

        if (isset($filters['paraphrase_status'])) {
            if ($filters['paraphrase_status'] === 'with') {
                $query->whereHas('paraphrases');
            } elseif ($filters['paraphrase_status'] === 'without') {
                $query->whereDoesntHave('paraphrases');
            }
        }

        $sortBy = $filters['sort_by'] ?? 'latest';

        match ($sortBy) {
            'oldest' => $query->orderBy($this->qualifyColumn('id'), 'asc'),
            'title', 'title_asc' => $query->orderBy($this->qualifyColumn('title'), 'asc')
                ->orderByDesc($this->qualifyColumn('id')),
            'title_desc' => $query->orderBy($this->qualifyColumn('title'), 'desc')
                ->orderByDesc($this->qualifyColumn('id')),
            'subject' => $query->orderBy($this->qualifyColumn('subject_tag'), 'asc')
                ->orderByDesc($this->qualifyColumn('id')),
            'paraphrases' => $query->orderByDesc(
                QuestionParaphrase::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('question_id', $this->qualifyColumn('id'))
            )->orderByDesc($this->qualifyColumn('id')),
            'points_asc' => $query->orderBy($this->qualifyColumn('default_points'), 'asc')
                ->orderByDesc($this->qualifyColumn('id')),
            'points_desc' => $query->orderBy($this->qualifyColumn('default_points'), 'desc')
                ->orderByDesc($this->qualifyColumn('id')),
            default => $query->latest($this->qualifyColumn('id')),
        };

        return $query;
    }

    /**
     * Aplica comparación tolerante sobre la columna subject_tag para compatibilidad
     * con reactivos históricos o pruebas sin subject_id asignado.
     *
     * @param Builder<Question> $query           Subconsulta Eloquent.
     * @param string            $subjectColumn   Columna calificada subject_tag.
     * @param string            $cleanSubject    Nombre limpio de la materia.
     * @param string            $normalized      Llave normalizada ASCII.
     * @return void
     */
    private function applyNormalizedTagEquals(
        Builder $query,
        string $subjectColumn,
        string $cleanSubject,
        string $normalized
    ): void {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql' && $normalized !== '') {
            $query->orWhereRaw(
                $this->normalizedSubjectSqlExpression($subjectColumn) . ' = ?',
                [$normalized]
            );

            return;
        }

        $query->orWhereRaw(
            "LOWER(TRIM(COALESCE({$subjectColumn}, ''))) = ?",
            [mb_strtolower($cleanSubject, 'UTF-8')]
        );
    }

    /**
     * Construye la expresión SQL PostgreSQL equivalente a Subject::normalizeName()
     * para filtrar etiquetas heredadas de forma insensible a acentos, mayúsculas y espacios.
     *
     * @param string $column Columna SQL calificada.
     * @return string Expresión SQL segura para consultas parametrizadas.
     */
    private function normalizedSubjectSqlExpression(string $column): string
    {
        return "trim(regexp_replace(translate(lower(regexp_replace(COALESCE({$column}, ''), E'[\\u200B\\u200C\\u200D\\uFEFF]', '', 'g')), 'áéíóúàèìòùäëïöüâêîôûãõñçÁÉÍÓÚÀÈÌÒÙÄËÏÖÜÂÊÎÔÛÃÕÑÇ', 'aeiouaeiouaeiouaeiouaoncaeiouaeiouaeiouaeiouaonc'), '[^a-z0-9+#]+', ' ', 'g'))";
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_points' => 'decimal:2',
        ];
    }
}
