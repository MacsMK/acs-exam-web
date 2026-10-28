<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     LoginController.php
 * Ubicación:   app/Http/Controllers/Auth/LoginController.php
 * Descripción: Login web en dos pasos: credenciales -> código 2FA ->
 *              sesión (guard "web"), con invalidación segura de sesión y
 *              regeneración de token CSRF en logout.
 * ============================================================================
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $user = $this->authService->attemptCredentials(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }

    public function showTwoFactorForm(User $user): View
    {
        return view('auth.verify-2fa', ['user' => $user]);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        $this->authService->verifyTwoFactorCode($user, $request->string('code')->toString());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request->user());

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login.show');
    }
}
