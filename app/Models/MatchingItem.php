<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     MatchingItem.php
 * Ubicación:   app/Models/MatchingItem.php
 * Descripción: Variante 2 del banco de preguntas (relación de columnas).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class MatchingItem extends Model
{
    protected $table = 'acs_matching_items';

    protected $fillable = [
        'left_column_payload',
        'right_column_payload',
        'pairs_payload',
    ];

    protected function casts(): array
    {
        return [
            'left_column_payload' => 'array',
            'right_column_payload' => 'array',
            'pairs_payload' => 'array',
        ];
    }

    /**
     * @return MorphOne<Question, MatchingItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }
}
