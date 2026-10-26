<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     RegisterController.php
 * Ubicación:   app/Http/Controllers/Auth/RegisterController.php
 * ============================================================================
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Requests\RegisterTeacherRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RegisterController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showTeacherForm(): View
    {
        return view('auth.register-teacher');
    }

    public function showStudentForm(): View
    {
        return view('auth.register-student');
    }

    public function storeTeacher(RegisterTeacherRequest $request): RedirectResponse
    {
        $user = $this->authService->register($request->validated(), 'teacher');

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }

    public function storeStudent(RegisterStudentRequest $request): RedirectResponse
    {
        $user = $this->authService->register($request->validated(), 'student');

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }
}
