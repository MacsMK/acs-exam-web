<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        EloquentAttemptRepository.php
 * Ubicación:      app/Repositories/Eloquent/EloquentAttemptRepository.php
 * Requerimiento:  RF-EXA / RF-CAL (Aplicación y Calificación, MPW-V2 §2.4 y §6.7)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 28/10/2026
 * Descripción:    Implementación Eloquent del repositorio de intentos de
 *                 examen y respuestas de alumnos con Eager Loading (with,
 *                 withCount), paginación en SQL y transacciones atómicas.
 * ============================================================================
 */

namespace App\Repositories\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

use App\Models\StudentAnswer;
use App\Models\StudentAttempt;
use App\Repositories\Contracts\AttemptRepositoryInterface;

/**
 * Repositorio Eloquent para intentos de examen y respuestas de alumnos.
 *
 * @package App\Repositories\Eloquent
 */
class EloquentAttemptRepository implements AttemptRepositoryInterface
{
    /**
     * Busca el intento de un alumno para un examen específico con relaciones precargadas.
     *
     * @param int $studentId Identificador del alumno.
     * @param int $examId    Identificador del examen.
     * @return StudentAttempt|null
     */
    public function findForStudentAndExam(int $studentId, int $examId): ?StudentAttempt
    {
        return StudentAttempt::query()
            ->with(['exam', 'answers.examQuestion.question'])
            ->withCount(['answers'])
            ->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();
    }

    /**
     * Busca un intento por su identificador con examen, grupo, alumno y respuestas precargadas.
     *
     * @param int $attemptId Identificador del intento.
     * @return StudentAttempt|null
     */
    public function findByIdWithDetails(int $attemptId): ?StudentAttempt
    {
        return StudentAttempt::query()
            ->with(['exam', 'group', 'student', 'answers.examQuestion.question.questionable'])
            ->withCount(['answers'])
            ->find($attemptId);
    }

    /**
     * Obtiene o crea de forma idempotente el intento en curso de un alumno para un examen.
     *
     * @param int             $examId           Identificador del examen.
     * @param int             $studentId        Identificador del alumno.
     * @param int             $groupId          Identificador del grupo del alumno.
     * @param array<int, int> $questionSequence Secuencia de ids de acs_exam_question.
     * @return StudentAttempt
     */
    public function firstOrCreateAttempt(
        int $examId,
        int $studentId,
        int $groupId,
        array $questionSequence
    ): StudentAttempt {
        return StudentAttempt::firstOrCreate(
            [
                'exam_id' => $examId,
                'student_id' => $studentId,
            ],
            [
                'group_id' => $groupId,
                'question_sequence_payload' => $questionSequence,
                'current_question_index' => 0,
                'started_at' => now(),
                'status' => 'in_progress',
            ]
        );
    }

    /**
     * Registra o actualiza la respuesta de un alumno a un reactivo y avanza el índice activo.
     *
     * @param StudentAttempt       $attempt        Intento activo del alumno.
     * @param int                  $examQuestionId Identificador del reactivo dentro del examen.
     * @param array<string, mixed> $attributes     Atributos calificados y payload de respuesta.
     * @return StudentAnswer
     */
    public function saveAnswer(StudentAttempt $attempt, int $examQuestionId, array $attributes): StudentAnswer
    {
        return DB::transaction(function () use ($attempt, $examQuestionId, $attributes): StudentAnswer {
            $answer = StudentAnswer::updateOrCreate(
                [
                    'student_attempt_id' => $attempt->id,
                    'exam_question_id' => $examQuestionId,
                ],
                array_merge($attributes, ['answered_at' => now()])
            );

            $attempt->increment('current_question_index');

            return $answer;
        });
    }

    /**
     * Marca un intento como enviado (submitted) almacenando sus puntajes calculados.
     *
     * @param StudentAttempt $attempt          Intento a finalizar.
     * @param float          $objectiveScore   Puntaje de reactivos objetivos.
     * @param float          $aiEstimatedScore Puntaje estimado por IA.
     * @param float|null     $finalScore       Calificación final (null si hay pendientes de IA).
     * @param int            $pendingAiCount   Cantidad de respuestas pendientes de evaluación IA.
     * @return StudentAttempt
     */
    public function markSubmitted(
        StudentAttempt $attempt,
        float $objectiveScore,
        float $aiEstimatedScore,
        ?float $finalScore,
        int $pendingAiCount
    ): StudentAttempt {
        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'objective_score' => $objectiveScore,
            'ai_estimated_score' => $aiEstimatedScore,
            'final_score' => $finalScore,
            'pending_ai_count' => $pendingAiCount,
        ]);

        return $attempt->fresh(['exam', 'answers.examQuestion.question']);
    }

    /**
     * Incrementa el contador de incidentes y cancela el intento si supera el límite permitido.
     *
     * @param StudentAttempt $attempt             Intento en curso.
     * @param int            $maxAllowedIncidents Máximo de incidentes tolerados por el examen.
     * @return StudentAttempt
     */
    public function recordIncident(StudentAttempt $attempt, int $maxAllowedIncidents): StudentAttempt
    {
        if ($attempt->status === 'in_progress') {
            $attempt->increment('incidents_count');
            $attempt->refresh();

            if ($attempt->incidents_count > $maxAllowedIncidents) {
                $attempt->update([
                    'status' => 'cancelled_by_incident',
                    'submitted_at' => now(),
                ]);
            }
        }

        return $attempt;
    }

    /**
     * Obtiene los intentos calificados de un alumno paginados en SQL con el examen precargado.
     *
     * @param int $studentId Identificador del alumno.
     * @param int $perPage   Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateGradedForStudent(int $studentId, int $perPage = 20): LengthAwarePaginator
    {
        return StudentAttempt::query()
            ->where('student_id', $studentId)
            ->whereNotNull('final_score')
            ->with(['exam', 'group'])
            ->withCount(['answers'])
            ->latest('submitted_at')
            ->paginate($perPage);
    }

    /**
     * Obtiene todos los intentos calificados de un alumno con el examen precargado (estadísticas).
     *
     * @param int $studentId Identificador del alumno.
     * @return Collection<int, StudentAttempt>
     */
    public function getGradedForStudent(int $studentId): Collection
    {
        return StudentAttempt::query()
            ->where('student_id', $studentId)
            ->whereNotNull('final_score')
            ->with(['exam', 'group'])
            ->withCount(['answers'])
            ->latest('submitted_at')
            ->get();
    }

    /**
     * Obtiene los intentos de un examen paginados en SQL con alumno y grupo precargados.
     *
     * @param int $examId  Identificador del examen.
     * @param int $perPage Registros por página en SQL.
     * @return LengthAwarePaginator<StudentAttempt>
     */
    public function paginateAttemptsForExam(int $examId, int $perPage = 20): LengthAwarePaginator
    {
        return StudentAttempt::query()
            ->where('exam_id', $examId)
            ->with(['student', 'group'])
            ->withCount(['answers'])
            ->latest('submitted_at')
            ->paginate($perPage);
    }
}
