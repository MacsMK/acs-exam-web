<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     OpenItem.php
 * Ubicación:   app/Models/OpenItem.php
 * Descripción: Variante 3 del banco de preguntas (pregunta abierta).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class OpenItem extends Model
{
    protected $table = 'acs_open_items';

    protected $fillable = [
        'reference_answer',
        'evaluation_rubric',
    ];

    /**
     * @return MorphOne<Question, OpenItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }
}
