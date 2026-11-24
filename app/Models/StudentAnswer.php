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
 * Versión:        1.2.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_student_answers (incluye evaluación IA,
 *                 trazabilidad de fallback/modelo, recotejo docente y snapshot
 *                 de la paráfrasis servida).
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Respuesta de un alumno a un reactivo dentro de un intento de examen.
 *
 * @package App\Models
 */
class StudentAnswer extends Model
{
    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_student_answers';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1).
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
        'ai_is_fallback',
        'ai_model_used',
        'teacher_recoteje_score',
        'teacher_recoteje_notes',
        'answered_at',
        'free_revision',
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
     * Registros de consumo de créditos de IA asociados a esta respuesta.
     *
     * @return HasMany<AiCreditUsage>
     */
    public function aiCreditUsages(): HasMany
    {
        return $this->hasMany(AiCreditUsage::class, 'student_answer_id');
    }

    /**
     * Identifica si una respuesta abierta o de código admite recoteje.
     *
     * @return bool Si existe texto evaluable y la entrega no se registró en blanco.
     */
    public function canBeReviewedQualitatively(): bool
    {
        return $this->ai_model_used !== 'none' && self::qualitativeText($this->answer_payload) !== '';
    }

    public function aiSuggestedPoints(): ?float
    {
        if ($this->ai_similarity_score === null || $this->ai_model_used === 'none'
            || ! in_array($this->examQuestion?->question?->question_type, ['open', 'code'], true)) {
            return null;
        }

        return self::pointsForAiSimilarity((float) $this->ai_similarity_score, (float) $this->examQuestion->points_weight);
    }

    public static function pointsForAiSimilarity(float $similarity, float $weight): float
    {
        return round($similarity / 100 * $weight, 2);
    }

    /**
     * Recupera el texto de las representaciones válidas de una respuesta cualitativa.
     *
     * @param mixed $payload Respuesta registrada en web o en la API.
     * @return string Texto recortado o cadena vacía si no hay respuesta.
     */
    public static function qualitativeText(mixed $payload): string
    {
        $text = is_string($payload) ? $payload : '';
        if (is_array($payload)) {
            foreach (['text', 'code', 'answer', 'given_answer', 'raw', 'source'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key])) {
                    $text = $payload[$key];
                    break;
                }
            }
        }

        return preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $text) ?? trim($text);
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
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
            'ai_is_fallback' => 'boolean',
            'teacher_recoteje_score' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }
}
