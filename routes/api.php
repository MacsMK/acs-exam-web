<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     api.php
 * Ubicación:   routes/api.php
 * Descripción: API v1 consumida por la app móvil acs-exam-mobile (Sanctum).
 * ============================================================================
 */

use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\GroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/register/teacher', [AuthController::class, 'registerTeacher'])->name('auth.register.teacher');
    Route::post('auth/register/student', [AuthController::class, 'registerStudent'])->name('auth.register.student');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/verify-2fa', [AuthController::class, 'verifyTwoFactor'])->name('auth.verify-2fa');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::post('groups/join', [GroupController::class, 'join'])->name('groups.join');
        Route::get('groups/mine', [GroupController::class, 'mine'])->name('groups.mine');

        Route::get('exams/assigned-to-me', [ExamController::class, 'assignedToMe'])->name('exams.assigned');

        Route::post('exams/{exam}/attempts/start', [AttemptController::class, 'start'])->name('attempts.start');
        Route::get('attempts/{attempt}/current-question', [AttemptController::class, 'currentQuestion'])->name('attempts.current-question');
        Route::post('attempts/{attempt}/answers', [AttemptController::class, 'submitAnswer'])->name('attempts.answers');
        Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submitFinal'])->name('attempts.submit');
    });
});
