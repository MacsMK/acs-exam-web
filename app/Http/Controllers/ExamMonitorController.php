<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ExamMonitorController.php
 * Ubicación:      app/Http/Controllers/ExamMonitorController.php
 * Requerimiento:  RW-12, RW-16, RNF-SEG-004 (Monitoreo Docente WebSockets Reverb)
 * Autor:          Equipo de Ingeniería (A-Code Solutions)
 * Versión:        1.1.0
 * Descripción:    Controlador web para el tablero de monitoreo y supervisión en
 *                 vivo de exámenes activos con conexión a Laravel Reverb.
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Contracts\View\View;

/**
 * Controlador web para la supervisión docente reactiva de exámenes en tiempo real.
 *
 * @package App\Http\Controllers
 */
class ExamMonitorController extends Controller
{
    /**
     * Muestra el tablero de monitoreo en tiempo real del examen.
     */
    public function show(Exam $exam): View
    {
        $this->authorizeTeacher($exam);

        $exam->load(['groups.students', 'studentAttempts.student']);

        // Alumnos matriculados únicos en todos los grupos del examen
        $enrolledStudents = $exam->groups
            ->flatMap(fn ($group) => $group->students)
            ->unique('id')
            ->values();

        $attempts = $exam->studentAttempts->keyBy('student_id');

        // Métricas iniciales calculadas sin N+1
        $totalEnrolled = $enrolledStudents->count();
        $inProgressCount = $attempts->where('status', 'in_progress')->count();
        $submittedCount = $attempts->where('status', 'submitted')->count();
        $cancelledCount = $attempts->where('status', 'cancelled_by_incident')->count();
        $totalIncidents = (int) $attempts->sum('incidents_count');

        $studentsPayload = $enrolledStudents->mapWithKeys(function ($student) use ($attempts): array {
            $att = $attempts->get($student->id);

            return [
                (string) $student->id => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $att?->status ?? 'not_started',
                    'questionIndex' => $att?->current_question_index ?? 0,
                    'incidents' => $att?->incidents_count ?? 0,
                    'score' => $att?->final_score !== null
                        ? number_format((float) $att->final_score, 2)
                        : ($att?->status === 'submitted' ? 'Pendiente' : '—'),
                    'started_at' => $att?->started_at?->format('H:i:s') ?? '—',
                    'submitted_at' => $att?->submitted_at?->format('H:i:s') ?? '—',
                ],
            ];
        })->all();

        $reverbConfig = [
            'key' => (string) config('broadcasting.connections.reverb.key', config('reverb.apps.apps.0.key', 'acsexamwebkey')),
            'host' => request()->getHost(),
            'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
            'scheme' => (string) config('broadcasting.connections.reverb.options.scheme', 'http'),
        ];

        return view('exams.monitor', [
            'exam' => $exam,
            'enrolledStudents' => $enrolledStudents,
            'studentsPayload' => $studentsPayload,
            'attempts' => $attempts,
            'totalEnrolled' => $totalEnrolled,
            'inProgressCount' => $inProgressCount,
            'submittedCount' => $submittedCount,
            'cancelledCount' => $cancelledCount,
            'totalIncidents' => $totalIncidents,
            'reverbConfig' => $reverbConfig,
        ]);
    }

    /**
     * Valida la propiedad del examen frente a ataques IDOR (RNF-SEG-004).
     */
    private function authorizeTeacher(Exam $exam): void
    {
        abort_unless($exam->teacher_id === request()->user()->id, 403);
    }
}
