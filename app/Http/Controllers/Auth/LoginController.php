<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     LoginController.php
 * Ubicación:   app/Http/Controllers/Auth/LoginController.php
 * Descripción: Login web en dos pasos: credenciales -> código 2FA ->
 *              sesión (guard "web", Fortify-style manual).
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

        $request->session()->put('pending_2fa_user_id', $user->id);

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un código de verificación.');
    }

    public function showTwoFactorForm(User $user): View
    {
        abort_unless(
            (int) session('pending_2fa_user_id') === $user->id,
            403
        );

        return view('auth.verify-2fa', ['user' => $user]);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): RedirectResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        abort_unless(
            (int) $request->session()->get('pending_2fa_user_id') === $user->id,
            403
        );

        $this->authService->verifyTwoFactorCode($user, $request->string('code')->toString());

        $request->session()->forget('pending_2fa_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        return redirect()->route('auth.login.show');
    }
}
