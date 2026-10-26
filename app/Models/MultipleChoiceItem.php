<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     MultipleChoiceItem.php
 * Ubicación:   app/Models/MultipleChoiceItem.php
 * Descripción: Variante 1 del banco de preguntas (opción múltiple).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MultipleChoiceItem extends Model
{
    protected $table = 'acs_multiple_choice_items';

    protected $fillable = [
        'options_payload',
        'correct_option_index',
    ];

    protected function casts(): array
    {
        return [
            'options_payload' => 'array',
        ];
    }

    /**
     * Reactivo genérico asociado (lado inverso del morphTo).
     *
     * @return MorphOne<Question, MultipleChoiceItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }
}
