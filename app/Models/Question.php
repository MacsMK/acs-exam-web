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
 * Versión:        1.2.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_questions (tabla raíz polimórfica
 *                 del banco de preguntas, Módulo 5) con aislamiento por
 *                 docente (MPW-V2 §2.3.4, §6.6), SoftDeletes y scopes de búsqueda.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reactivo raíz polimórfico del banco de preguntas de un docente.
 *
 * @package App\Models
 */
class Question extends Model
{
    use SoftDeletes;

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_questions';

    /**
     * Columnas asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
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
     * Docente autor del reactivo.
     *
     * @return BelongsTo<User, Question>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
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
     * Variantes de redacción (anti-copia) de este reactivo canónico.
     *
     * @return HasMany<QuestionParaphrase>
     */
    public function paraphrases(): HasMany
    {
        return $this->hasMany(QuestionParaphrase::class);
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
     * Scope local para búsqueda textual en título, redacción (stem) y materia (subject_tag).
     * Emplea ILIKE en PostgreSQL y LIKE en motores que no lo soportan.
     *
     * @param Builder<Question> $query Constructor de la consulta Eloquent.
     * @param string|null       $term  Término de búsqueda ingresado por el docente.
     * @return Builder<Question>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $cleanTerm = trim((string) $term);
        if ($cleanTerm === '') {
            return $query;
        }

        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $searchPattern = '%' . $cleanTerm . '%';

        return $query->where(function (Builder $subQuery) use ($likeOperator, $searchPattern): void {
            $subQuery->where($this->qualifyColumn('title'), $likeOperator, $searchPattern)
                ->orWhere($this->qualifyColumn('stem'), $likeOperator, $searchPattern)
                ->orWhere($this->qualifyColumn('subject_tag'), $likeOperator, $searchPattern);
        });
    }

    /**
     * Scope local para aplicar filtros avanzados de filtrado y ordenamiento.
     *
     * @param Builder<Question>    $query   Constructor de la consulta Eloquent.
     * @param array<string, mixed> $filters Filtros aplicables (question_type, subject_tag, paraphrase_status, sort_by).
     * @return Builder<Question>
     */
    public function scopeFilterBy(Builder $query, array $filters): Builder
    {
        if (
            !empty($filters['question_type']) &&
            in_array($filters['question_type'], ['multiple_choice', 'matching', 'open', 'code'], true)
        ) {
            $query->where($this->qualifyColumn('question_type'), $filters['question_type']);
        }

        if (!empty($filters['subject_tag'])) {
            $query->where($this->qualifyColumn('subject_tag'), $filters['subject_tag']);
        }

        if (isset($filters['paraphrase_status'])) {
            if ($filters['paraphrase_status'] === 'with') {
                $query->whereHas('paraphrases');
            } elseif ($filters['paraphrase_status'] === 'without') {
                $query->whereDoesntHave('paraphrases');
            }
        }

        $sortBy = $filters['sort_by'] ?? 'recent';
        match ($sortBy) {
            'points_asc' => $query->orderBy($this->qualifyColumn('default_points'), 'asc'),
            'points_desc' => $query->orderBy($this->qualifyColumn('default_points'), 'desc'),
            'title_asc' => $query->orderBy($this->qualifyColumn('title'), 'asc'),
            'title_desc' => $query->orderBy($this->qualifyColumn('title'), 'desc'),
            default => $query->latest($this->qualifyColumn('id')),
        };

        return $query;
    }

    /**
     * Conversión de atributos (Getters/Setters y casts al pie según MPW-V2 Tabla 6 Bloque 16).
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
