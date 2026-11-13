<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        QuestionParaphrase.php
 * Ubicación:      app/Models/QuestionParaphrase.php
 * Requerimiento:  RF-BAN-010 (Paráfrasis Anti-Copia, Módulo 5)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_question_paraphrases (variantes de
 *                 redacción anti-copia de un reactivo canónico).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variante de redacción (paráfrasis anti-copia) de un reactivo canónico.
 *
 * @package App\Models
 */
class QuestionParaphrase extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_question_paraphrases';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
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

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_id' => 'integer',
        ];
    }
}
