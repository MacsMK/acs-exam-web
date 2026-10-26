<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     web.php
 * Ubicación:   routes/web.php
 * Descripción: Panel web (docente/admin) para que el profesor pueda
 *              probar el flujo completo dando clic, sin Postman.
 * ============================================================================
 */

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('register/docente', [RegisterController::class, 'showTeacherForm'])->name('auth.register.teacher.show');
    Route::post('register/docente', [RegisterController::class, 'storeTeacher'])->name('auth.register.teacher.store');
    Route::get('register/alumno', [RegisterController::class, 'showStudentForm'])->name('auth.register.student.show');
    Route::post('register/alumno', [RegisterController::class, 'storeStudent'])->name('auth.register.student.store');

    Route::get('login', [LoginController::class, 'showLoginForm'])->name('auth.login.show');
    Route::post('login', [LoginController::class, 'login'])->name('auth.login.store');

    Route::get('verificar-codigo/{user}', [LoginController::class, 'showTwoFactorForm'])->name('auth.verify-2fa.show');
    Route::post('verificar-codigo', [LoginController::class, 'verifyTwoFactor'])->name('auth.verify-2fa.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'logout'])->name('auth.logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:teacher')->group(function (): void {
        Route::resource('groups', GroupController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('groups/{group}/cerrar', [GroupController::class, 'close'])->name('groups.close');

        Route::resource('questions', QuestionController::class)->only(['index', 'create', 'store']);
        Route::post('questions/{question}/parafrasis', [QuestionController::class, 'storeParaphrase'])->name('questions.paraphrase.store');

        Route::resource('exams', ExamController::class)->only(['index', 'create', 'store']);
        Route::get('exams/{exam}/armar', [ExamController::class, 'assemble'])->name('exams.assemble');
        Route::post('exams/{exam}/apartados', [ExamController::class, 'storeSection'])->name('exams.sections.store');
        Route::post('exams/{exam}/reactivos/{examQuestion}/nuevo-sorteo', [ExamController::class, 'reshuffleQuestion'])->name('exams.questions.reshuffle');
        Route::post('exams/{exam}/publicar', [ExamController::class, 'publish'])->name('exams.publish');
    });
});
