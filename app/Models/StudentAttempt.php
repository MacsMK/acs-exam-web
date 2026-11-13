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
 * Versión:        1.2.0
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
     * Tabla asociada (prefijo institucional acs_, MBD-V4 §3.4).
     *
     * @var string
     */
    protected $table = 'acs_student_attempts';

    /**
     * Columnas asignables en masa (MPW-V2 §2.3.1).
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
        'submitted_at',
        'status',
        'objective_score',
        'ai_estimated_score',
        'final_score',
        'pending_ai_count',
        'incidents_count',
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

    /**
     * Limita los intentos a los de grupos a los que el alumno pertenece hoy.
     * Al salir de un grupo (RF-GRU-012) sus resultados dejan de ser visibles
     * para él, pero se conservan como evidencia académica (ADR-036) y
     * reaparecen si vuelve a unirse.
     *
     * @param Builder<StudentAttempt> $query     Constructor de la consulta Eloquent.
     * @param int                     $studentId Identificador del estudiante.
     * @return Builder<StudentAttempt>
     */
    public function scopeInCurrentGroupsOfStudent(Builder $query, int $studentId): Builder
    {
        return $query->whereIn('group_id', static function ($sub) use ($studentId): void {
            $sub->select('group_id')->from('acs_group_student')->where('student_id', $studentId);
        });
    }

    /**
     * Conversión de atributos (MPW-V2 Tabla 6 Bloque 16).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_sequence_payload' => 'array',
            'paraphrase_snapshot_payload' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
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
