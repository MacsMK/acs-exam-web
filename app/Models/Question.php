<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     Question.php
 * Ubicación:   app/Models/Question.php
 * Descripción: Modelo Eloquent de acs_questions (tabla raíz polimórfica
 *              del banco de preguntas, Módulo 5).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Question extends Model
{
    protected $table = 'acs_questions';

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
}
