<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamSection.php
 * Ubicación:      app/Models/ExamSection.php
 * Requerimiento:  RF-EXA (Apartados de Examen, RW-12)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_exam_sections con enlace opcional a
 *                 materia normalizada (subject_id / subject_tag).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apartado o sección temática de un examen armado.
 *
 * @package App\Models
 */
class ExamSection extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_exam_sections';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'exam_id',
        'title',
        'question_type',
        'subject_tag',
        'subject_id',
        'requested_quantity',
        'sort_order',
    ];

    /**
     * Examen propietario de este apartado.
     *
     * @return BelongsTo<Exam, ExamSection>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Asignatura normalizada asociada opcionalmente al sorteo del apartado.
     *
     * @return BelongsTo<Subject, ExamSection>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Reactivos sorteados y aceptados dentro del apartado.
     *
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
