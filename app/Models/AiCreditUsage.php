<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AiCreditUsage.php
 * Ubicación:      app/Models/AiCreditUsage.php
 * Requerimiento:  RF-IAR-011 / RF-IAR-014 / MC-V2 (Consumo de créditos IA)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Modelo Eloquent de acs_ai_credit_usages: bitácora del
 *                 consumo de créditos de evaluación con IA por docente, con
 *                 vínculo opcional a la respuesta evaluada y trazabilidad del
 *                 modelo utilizado.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de consumo de créditos de evaluación con IA asociado a un docente.
 *
 * @package App\Models
 */
class AiCreditUsage extends Model
{
    /**
     * Estado que indica que el crédito fue consumido efectivamente.
     */
    public const STATUS_CONSUMED = 'consumed';

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_ai_credit_usages';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1: $guarded = [] prohibido).
     *
     * @var list<string>
     */
    protected $fillable = [
        'teacher_id',
        'student_answer_id',
        'credits_consumed',
        'is_reevaluation',
        'model_used',
        'status',
    ];

    /**
     * Docente al que se carga el consumo del crédito de IA.
     *
     * @return BelongsTo<User, AiCreditUsage>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Respuesta de alumno evaluada mediante IA (si aún existe).
     *
     * @return BelongsTo<StudentAnswer, AiCreditUsage>
     */
    public function studentAnswer(): BelongsTo
    {
        return $this->belongsTo(StudentAnswer::class, 'student_answer_id');
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits_consumed' => 'integer',
            'is_reevaluation' => 'boolean',
        ];
    }
}
