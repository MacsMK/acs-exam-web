<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     DashboardController.php
 * Ubicación:   app/Http/Controllers/DashboardController.php
 * Descripción: Panel de resumen operativo post-login (Módulo 2).
 * ============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->role === 'teacher') {
            return view('dashboard.teacher', [
                'groupsCount' => $user->groupsAsTeacher()->count(),
                'questionsCount' => $user->questionsAuthored()->count(),
                'examsCount' => $user->examsCreated()->count(),
            ]);
        }

        return view('dashboard.student', [
            'groupsCount' => $user->groupsAsStudent()->count(),
        ]);
    }
}
