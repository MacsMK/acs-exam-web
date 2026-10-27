<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     api.php
 * Ubicación:   routes/api.php
 * Descripción: API v1 consumida por la app móvil acs-exam-mobile (Sanctum).
 *              Contrato alineado 1:1 contra ApiEndpoints
 *              (acs-exam-mobile, lib/core/network/api_endpoints.dart,
 *              MPM-V4 Tabla 12) — ver docs/04_DECISIONES_ADR.md para el
 *              detalle de cada punto que estaba "pendiente de
 *              confirmación con el equipo backend" (DE-03) y cómo se
 *              resolvió del lado del servidor.
 * ============================================================================
 */

use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\PrivacyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/register/teacher', [AuthController::class, 'registerTeacher'])->name('auth.register.teacher');
    Route::post('auth/register/student', [AuthController::class, 'registerStudent'])->name('auth.register.student');
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/two-factor', [AuthController::class, 'twoFactor'])->name('auth.two-factor');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('privacy-notice', [PrivacyController::class, 'show'])->name('privacy.show');
        Route::post('privacy-notice/accept', [PrivacyController::class, 'accept'])->name('privacy.accept');

        Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
        Route::post('groups/join', [GroupController::class, 'join'])->name('groups.join');
        Route::post('groups/verify', [GroupController::class, 'verify'])->name('groups.verify');
        Route::get('groups/{group}/students', [GroupController::class, 'students'])->name('groups.students');

        Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
        Route::get('exams/{exam}/progress', [ExamController::class, 'progress'])->name('exams.progress');
        Route::post('exams/take', [AttemptController::class, 'take'])->name('exams.take');
        Route::post('exams/{exam}/answers', [AttemptController::class, 'answers'])->name('exams.answers');
        Route::post('exams/{exam}/incidents', [AttemptController::class, 'incidents'])->name('exams.incidents');

        Route::get('grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('grades/statistics', [GradeController::class, 'statistics'])->name('grades.statistics');

        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    });
});
