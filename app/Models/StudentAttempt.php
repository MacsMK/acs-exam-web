<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        StudentAttempt.php
 * Ubicación:      app/Models/StudentAttempt.php
 * Requerimiento:  RF-EXA / RF-EVA (Intento de Examen y Snapshot Anti-Copia)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.3.0
 * Fecha Creación: 26/10/2026
 * Descripción:    Modelo Eloquent de acs_student_attempts con secuencia de
 *                 preguntas y snapshot inmutable de paráfrasis asignadas.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Intento de resolución de examen realizado por un estudiante.
 *
 * @package App\Models
 */
class StudentAttempt extends Model
{
    /**
     * Acción de bitácora que identifica un intento finalizado porque el docente
     * detuvo el examen (RF-EXA-024).
     */
    public const ACTION_STOPPED_BY_TEACHER = 'attempt.stopped_by_teacher';

    /**
     * Acción de bitácora de la autorización de un intento adicional
     * (RF-EXA-052); registra qué docente la otorgó.
     */
    public const ACTION_REAPPLICATION_AUTHORIZED = 'attempt.reapplication_authorized';

    /**
     * Indica si el intento terminó porque el docente detuvo el examen.
     */
    public function wasStoppedByTeacher(): bool
    {
        return $this->status === 'expired' && \App\Models\AuditLog::query()
            ->where('auditable_type', self::class)
            ->where('auditable_id', $this->id)
            ->where('action', self::ACTION_STOPPED_BY_TEACHER)
            ->exists();
    }

    /**
     * Tabla asociada (prefijo institucional acs_, MBD-V3 §3.3).
     *
     * @var string
     */
    protected $table = 'acs_student_attempts';

    /**
     * Columnas asignables en masa (MPW-V3 §2.3.1).
     *
     * @var list<string>
     */
    protected $fillable = [
        'exam_id',
        'student_id',
        'group_id',
        'question_sequence_payload',
        'paraphrase_snapshot_payload',
        'current_question_index',
        'started_at',
        'free_edit_until',
        'free_close_at',
        'submitted_at',
        'status',
        'objective_score',
        'ai_estimated_score',
        'final_score',
        'pending_ai_count',
        'incidents_count',
        'attempt_number',
        'reapplication_authorized_at',
    ];

    /**
     * Examen al que corresponde este intento.
     *
     * @return BelongsTo<Exam, StudentAttempt>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Estudiante que presenta el examen.
     *
     * @return BelongsTo<User, StudentAttempt>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Grupo académico desde el cual se registró el intento.
     *
     * @return BelongsTo<Group, StudentAttempt>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Respuestas individuales emitidas durante este intento.
     *
     * @return HasMany<StudentAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function scopePendingTeacherReview(Builder $query): Builder
    {
        return $query->current()->where('status', 'submitted')->where('pending_ai_count', '>', 0);
    }

    public function scopeWithCurrentFinalGrade(Builder $query): Builder
    {
        return $query->current()->whereNotNull('final_score');
    }

    public function aiSuggestedScore(): ?float
    {
        $points = $this->answers->map->aiSuggestedPoints()->whereNotNull();

        return $points->isEmpty() ? null : round((float) $points->sum(), 2);
    }

    /**
     * Limita los intentos a grupos abiertos de los que el alumno es miembro.
     * Al perder el acceso (RF-GRU-011/012/019), los resultados se conservan
     * como evidencia académica (ADR-036).
     *
     * @param Builder<StudentAttempt> $query     Constructor de la consulta Eloquent.
     * @param int                     $studentId Identificador del estudiante.
     * @return Builder<StudentAttempt>
     */
    public function scopeInCurrentGroupsOfStudent(Builder $query, int $studentId): Builder
    {
        return $query->whereHas('group', static function (Builder $group) use ($studentId): void {
            $group->active()->whereHas('students', static fn (Builder $student) => $student->whereKey($studentId));
        });
    }

    /**
     * Limita la consulta al intento vigente de cada alumno en cada examen: el
     * de mayor attempt_number (RF-EXA-052). Los intentos reemplazados por una
     * reaplicación se conservan, pero no cuentan para calificaciones,
     * promedios ni estadísticas.
     *
     * @param Builder<StudentAttempt> $query Constructor de la consulta Eloquent.
     * @return Builder<StudentAttempt>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNotExists(static function ($sub): void {
            $sub->selectRaw('1')
                ->from('acs_student_attempts as newer_attempt')
                ->whereColumn('newer_attempt.exam_id', 'acs_student_attempts.exam_id')
                ->whereColumn('newer_attempt.student_id', 'acs_student_attempts.student_id')
                ->whereColumn('newer_attempt.attempt_number', '>', 'acs_student_attempts.attempt_number');
        });
    }

    /**
     * Conversión de atributos (MPW-V3 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_sequence_payload' => 'array',
            'paraphrase_snapshot_payload' => 'array',
            'started_at' => 'datetime',
            'free_edit_until' => 'datetime',
            'free_close_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reapplication_authorized_at' => 'datetime',
            'attempt_number' => 'integer',
            'objective_score' => 'decimal:2',
            'ai_estimated_score' => 'decimal:2',
            'final_score' => 'decimal:2',
        ];
    }

    /**
     * Calificación a desplegar en vista (final_score o objective_score si no hay final).
     *
     * @return float|null
     */
    public function getDisplayScoreAttribute(): ?float
    {
        return $this->final_score !== null
            ? (float) $this->final_score
            : ($this->objective_score !== null ? (float) $this->objective_score : null);
    }
}
