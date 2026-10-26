<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     CodeItem.php
 * Ubicación:   app/Models/CodeItem.php
 * Descripción: Variante 4 del banco de preguntas (programación).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CodeItem extends Model
{
    protected $table = 'acs_code_items';

    protected $fillable = [
        'programming_language',
        'is_find_error',
        'starter_code',
        'reference_solution',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'is_find_error' => 'boolean',
        ];
    }

    /**
     * @return MorphOne<Question, CodeItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }
}
