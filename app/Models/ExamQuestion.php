<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamQuestion.php
 * Ubicación:      app/Models/ExamQuestion.php
 * Requerimiento:  RF-EXA (Armado de Exámenes, RW-16/17/18)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_exam_question (pivote enriquecida:
 *                 reactivo + tiempo + ponderación dentro de un examen/apartado).
 *                 Incluye withTrashed() en question() para preservar integridad
 *                 histórica ante reactivos eliminados lógicamente.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reactivo asignado con tiempo y ponderación dentro de un examen.
 *
 * @package App\Models
 */
class ExamQuestion extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_exam_question';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'exam_id',
        'exam_section_id',
        'question_id',
        'time_limit_seconds',
        'points_weight',
        'sort_order',
    ];

    /**
     * Examen al que pertenece este reactivo asignado.
     *
     * @return BelongsTo<Exam, ExamQuestion>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Apartado del examen al que pertenece el reactivo.
     *
     * @return BelongsTo<ExamSection, ExamQuestion>
     */
    public function examSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class);
    }

    /**
     * Reactivo canónico vinculado (incluye eliminados lógicamente para revisión histórica).
     *
     * @return BelongsTo<Question, ExamQuestion>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /**
     * Respuestas de alumnos a este reactivo dentro del examen.
     *
     * @return HasMany<StudentAnswer>
     */
    public function studentAnswers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_weight' => 'decimal:2',
        ];
    }
}
