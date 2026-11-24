<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentExamController.php
 * Ubicación:   app/Http/Controllers/StudentExamController.php
 * Descripción: Versión web (sesión) del lado alumno: unirse a un grupo por
 *              folio y ver los exámenes publicados asignados (MPW-V3 §6.1).
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\GroupJoinRequest;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\GroupEnrollmentService;
use App\Services\StudentExamAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class StudentExamController extends Controller
{
    public function __construct(
        private readonly GroupEnrollmentService $groupEnrollmentService,
        private readonly StudentExamAvailability $availability,
    ) {
    }

    public function joinForm(): View
    {
        return view('groups.join');
    }

    public function join(GroupJoinRequest $request): RedirectResponse
    {
        $group = $this->groupEnrollmentService->joinByFolio(
            $request->user(),
            $request->resolvedFolio(),
            'access_folio',
        );

        return redirect()->route('student.groups.index')->with('status', 'Te uniste al grupo '.$group->name.'.');
    }

    public function index(Request $request): View|Response
    {
        $student = $request->user();
        $sectionTitles = [
            'in_progress' => 'Exámenes en progreso',
            Exam::STATUS_ACTIVE => 'Exámenes activos',
            Exam::STATUS_SCHEDULED => 'Exámenes programados',
            Exam::STATUS_CLOSED => 'Exámenes cerrados y finalizados',
        ];
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 200) : '';
        $status = $request->query('status');
        $status = is_string($status) && isset($sectionTitles[$status]) ? $status : 'all';
        $hasActiveFilters = $search !== '' || $status !== 'all';
        $term = Subject::normalizeName($search);
        $rows = $this->availability->forStudent($student);

        $matches = $rows->filter(static function (array $row) use ($status, $term): bool {
            if ($status !== 'all' && $status !== $row['section']) {
                return false;
            }

            $exam = $row['exam'];
            $text = $exam->title.' '.$exam->description.' '.$exam->groups->pluck('name')->implode(' ');

            return $term === '' || str_contains(Subject::normalizeName($text), $term);
        })->values();

        $pageSize = 12;
        $page = min(LengthAwarePaginator::resolveCurrentPage(), max(1, (int) ceil($matches->count() / $pageSize)));
        $exams = (new LengthAwarePaginator($matches->forPage($page, $pageSize), $matches->count(), $pageSize, $page, [
            'path' => route('student.exams.index'),
            'query' => array_filter(['search' => $search, 'status' => $status === 'all' ? null : $status], filled(...)),
        ]))->onEachSide(1);

        $view = view('exams.student-index', [
            'exams' => $exams,
            'sectionTitles' => $sectionTitles,
            'sections' => $exams->getCollection()->groupBy('section'),
            'hasAssignedExams' => $rows->isNotEmpty(),
            'hasGroups' => $rows->isNotEmpty() || $student->groupsAsStudent()->active()->exists(),
            'hasActiveFilters' => $hasActiveFilters,
            'search' => $search,
            'status' => $status,
            'timeZone' => Exam::timezoneLabel(),
        ]);

        if ($request->header('X-Catalog-Fragment') === 'student-exam-results') {
            return response($view->fragment('student-exam-results'))->header('Cache-Control', 'no-store');
        }

        return $view;
    }

    /**
     * Pantalla previa de instrucciones y condiciones antes de iniciar el cronómetro (RF-EXA-028 / P-83).
     */
    public function instructions(Request $request, Exam $exam): View|RedirectResponse
    {
        $row = $this->availability->forExam($exam, $request->user());
        abort_unless($row !== null, 403, 'No tienes permiso para acceder a este examen.');

        if (in_array($row['action'], ['resume', 'result'], true)) {
            return redirect()->to($row['href']);
        }

        return view('student.exams.instructions', [
            'exam' => $row['exam'],
            'questionsCount' => $row['exam']->exam_questions_count,
            'durationMinutes' => $row['durationMinutes'],
            'reapplicationNumber' => $row['reapplicationNumber'],
            'row' => $row,
            'timeZone' => Exam::timezoneLabel(),
        ]);
    }
}
