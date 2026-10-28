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
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_questions (tabla raíz polimórfica
 *                 del banco de preguntas, Módulo 5) con aislamiento por
 *                 docente (MPW-V2 §2.3.4, §6.6).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reactivo raíz polimórfico del banco de preguntas de un docente.
 *
 * @package App\Models
 */
class Question extends Model
{
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
     * Conversión de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_points' => 'decimal:2',
        ];
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
     * Pregunta original de la que esta es una edición (versionado en el
     * armado, RW-15). No confundir con paráfrasis (anti-copia): ver
     * paraphrases().
     *
     * @return BelongsTo<Question, Question>
     */
    public function parentQuestion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_question_id');
    }

    /**
     * Ediciones posteriores de este reactivo.
     *
     * @return HasMany<Question>
     */
    public function childQuestions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_question_id');
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
}
