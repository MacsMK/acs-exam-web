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
        $privacyNotice = $this->authService->currentPrivacyNotice();

        return view('auth.register-teacher', compact('privacyNotice'));
    }

    public function showStudentForm(): View
    {
        $privacyNotice = $this->authService->currentPrivacyNotice();

        return view('auth.register-student', compact('privacyNotice'));
    }

    public function storeTeacher(RegisterTeacherRequest $request): RedirectResponse
    {
        $user = $this->authService->registerWeb(
            $request->validated(),
            'teacher',
            $request->input('privacy_notice_id'),
            $request->ip(),
        );

        $request->session()->put('pending_2fa_user_id', $user->id);

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }

    public function storeStudent(RegisterStudentRequest $request): RedirectResponse
    {
        $user = $this->authService->registerWeb(
            $request->validated(),
            'student',
            $request->input('privacy_notice_id'),
            $request->ip(),
        );

        $request->session()->put('pending_2fa_user_id', $user->id);
        
        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }
}
