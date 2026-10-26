<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     QuestionParaphrase.php
 * Ubicación:   app/Models/QuestionParaphrase.php
 * Descripción: Modelo Eloquent de acs_question_paraphrases.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionParaphrase extends Model
{
    protected $table = 'acs_question_paraphrases';

    protected $fillable = [
        'question_id',
        'stem',
    ];

    /**
     * Reactivo canónico del que es variante.
     *
     * @return BelongsTo<Question, QuestionParaphrase>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
