<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        StudentAnswer.php
 * Ubicación:      app/Models/StudentAnswer.php
 * Requerimiento:  RF-EVA / RF-REP-005 (Respuesta, IA, Recotejo y Snapshot)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.1.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_student_answers (incluye evaluación IA,
 *                 recotejo docente y snapshot de la paráfrasis servida).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta de un alumno a un reactivo dentro de un intento de examen.
 *
 * @package App\Models
 */
class StudentAnswer extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_student_answers';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_attempt_id',
        'exam_question_id',
        'question_paraphrase_id',
        'served_stem_snapshot',
        'answer_payload',
        'is_correct',
        'points_awarded',
        'ai_similarity_score',
        'ai_meets_goal',
        'ai_feedback',
        'ai_evaluated_at',
        'teacher_recoteje_score',
        'teacher_recoteje_notes',
        'answered_at',
    ];

    /**
     * Intento de examen al que pertenece la respuesta.
     *
     * @return BelongsTo<StudentAttempt, StudentAnswer>
     */
    public function studentAttempt(): BelongsTo
    {
        return $this->belongsTo(StudentAttempt::class);
    }

    /**
     * Reactivo del examen respondido.
     *
     * @return BelongsTo<ExamQuestion, StudentAnswer>
     */
    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class);
    }

    /**
     * Paráfrasis específica servida al alumno al momento de responder (si aplica).
     *
     * @return BelongsTo<QuestionParaphrase, StudentAnswer>
     */
    public function paraphrase(): BelongsTo
    {
        return $this->belongsTo(QuestionParaphrase::class, 'question_paraphrase_id');
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
            'ai_similarity_score' => 'decimal:2',
            'ai_meets_goal' => 'boolean',
            'ai_evaluated_at' => 'datetime',
            'teacher_recoteje_score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }
}
