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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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

        return view('auth.verify-2fa', [
            'user' => $user,
            'demoCode' => $this->authService->demoTwoFactorCode($user),
            'expiresIn' => $user->two_factor_expires_at !== null
                ? max(0, $user->two_factor_expires_at->getTimestamp() - now()->getTimestamp())
                : 0,
            'resendIn' => $this->authService->secondsUntilResendAvailable($user),
        ]);
    }

    /**
     * Reenvía el código del segundo paso a la cuenta con verificación pendiente
     * en la sesión (RF-AUT-006). Respeta la espera de 60 segundos e invalida
     * el código anterior.
     *
     * @param Request $request Solicitud con la sesión de verificación pendiente.
     * @return RedirectResponse
     */
    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $user = User::find((int) $request->session()->get('pending_2fa_user_id'));

        abort_if($user === null, 403);

        try {
            if ($this->authService->resendTwoFactorCodeForUser($user) === null) {
                return redirect()->route('auth.login.show');
            }
        } catch (TooManyRequestsHttpException | ValidationException $exception) {
            return redirect()
                ->route('auth.verify-2fa.show', ['user' => $user->id])
                ->withErrors(['code' => $exception instanceof ValidationException
                    ? ($exception->errors()['email'][0] ?? $exception->getMessage())
                    : $exception->getMessage()]);
        }

        return redirect()
            ->route('auth.verify-2fa.show', ['user' => $user->id])
            ->with('status', 'Te enviamos un nuevo código de verificación. El anterior ya no es válido.');
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

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request->user());

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login.show');
    }
}
