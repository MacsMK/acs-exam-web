<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        MultipleChoiceItem.php
 * Ubicación:      app/Models/MultipleChoiceItem.php
 * Requerimiento:  RF-BAN (Banco de Preguntas Polimórfico, Variante 1)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_multiple_choice_items (variante
 *                 polimórfica de opción múltiple, Módulo 5).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Variante de opción múltiple vinculada polimórficamente a Question.
 *
 * @package App\Models
 */
class MultipleChoiceItem extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_multiple_choice_items';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'options_payload',
        'correct_option_index',
    ];

    /**
     * Reactivo genérico asociado (lado inverso del morphTo).
     *
     * @return MorphOne<Question, MultipleChoiceItem>
     */
    public function question(): MorphOne
    {
        return $this->morphOne(Question::class, 'questionable');
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options_payload' => 'array',
        ];
    }
}
