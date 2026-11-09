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

use App\Http\Controllers\AttemptController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationPeriodController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamMonitorController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentExamController;
use App\Http\Controllers\TeacherReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

Route::middleware('guest')->group(function (): void {
    Route::get('register/docente', [RegisterController::class, 'showTeacherForm'])->name('auth.register.teacher.show');
    Route::post('register/docente', [RegisterController::class, 'storeTeacher'])->name('auth.register.teacher.store');
    Route::get('register/alumno', [RegisterController::class, 'showStudentForm'])->name('auth.register.student.show');
    Route::post('register/alumno', [RegisterController::class, 'storeStudent'])->name('auth.register.student.store');

    Route::get('login', [LoginController::class, 'showLoginForm'])->name('auth.login.show');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('auth.login.store');

    Route::get('verificar-codigo/{user}', [LoginController::class, 'showTwoFactorForm'])->name('auth.verify-2fa.show');
    Route::post('verificar-codigo', [LoginController::class, 'verifyTwoFactor'])->middleware('throttle:10,1')->name('auth.verify-2fa.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'logout'])->name('auth.logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:teacher')->group(function (): void {
        Route::resource('groups', GroupController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('groups/{group}/cerrar', [GroupController::class, 'close'])->name('groups.close');
        Route::post('groups/{group}/reabrir', [GroupController::class, 'reopen'])->name('groups.reopen');
        Route::get('groups/{group}/periodos', [EvaluationPeriodController::class, 'index'])->name('groups.periods.index');
        Route::post('groups/{group}/periodos', [EvaluationPeriodController::class, 'store'])->name('groups.periods.store');
        Route::get('groups/{group}/periodos/{period}/sabana', [EvaluationPeriodController::class, 'gradebook'])->name('groups.periods.gradebook');
        Route::post('groups/{group}/periodos/{period}/sabana', [EvaluationPeriodController::class, 'saveGradebook'])->name('groups.periods.gradebook.save');
        Route::get('groups/{group}/periodos/{period}/sabana/export', [EvaluationPeriodController::class, 'exportGradebook'])->name('groups.periods.gradebook.export');

        Route::get('questions/template/csv', [QuestionController::class, 'downloadTemplate'])->name('questions.template.csv');
        Route::post('questions/import', [QuestionController::class, 'import'])->name('questions.import');
        Route::post('questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
        Route::post('questions/{question}/parafrasis', [QuestionController::class, 'storeParaphrase'])->name('questions.paraphrase.store');
        Route::delete('questions/{question}/parafrasis/{paraphrase}', [QuestionController::class, 'destroyParaphrase'])->name('questions.paraphrase.destroy');
        Route::resource('questions', QuestionController::class);

        Route::resource('exams', ExamController::class)->only(['index', 'create', 'store']);
        Route::get('exams/{exam}/armar', [ExamController::class, 'assemble'])->name('exams.assemble');
        Route::post('exams/{exam}/apartados', [ExamController::class, 'storeSection'])->name('exams.sections.store');
        Route::post('exams/{exam}/propuesta', [ExamController::class, 'generateSectionProposal'])->name('exams.proposals.generate');
        Route::post('exams/{exam}/propuesta/aceptar', [ExamController::class, 'acceptSectionProposal'])->name('exams.proposals.accept');
        Route::post('exams/{exam}/propuesta/descartar', [ExamController::class, 'discardSectionProposal'])->name('exams.proposals.discard');
        Route::post('exams/{exam}/reactivos/{examQuestion}/nuevo-sorteo', [ExamController::class, 'reshuffleQuestion'])->name('exams.questions.reshuffle');
        Route::post('exams/{exam}/publicar', [ExamController::class, 'publish'])->name('exams.publish');
        Route::get('exams/{exam}/configuracion', [ExamController::class, 'configuration'])->name('exams.configuration');
        Route::put('exams/{exam}/configuracion', [ExamController::class, 'updateConfiguration'])->name('exams.configuration.update');
        Route::get('exams/{exam}/revisar', [ExamController::class, 'review'])->name('exams.review');
        Route::get('exams/{exam}/monitoreo', [ExamMonitorController::class, 'show'])->name('exams.monitor');
        Route::get('exams/{exam}/revision', [TeacherReviewController::class, 'index'])->name('exams.revision.index');
        Route::get('exams/{exam}/revision/{attempt}', [TeacherReviewController::class, 'show'])->name('exams.revision.show');
        Route::post('exams/{exam}/revision/{attempt}', [TeacherReviewController::class, 'update'])->name('exams.revision.update');
        Route::get('docente/proximamente/{section}', function (string $section) {
            $sections = [
                'revision' => 'Revisión',
                'calificaciones' => 'Calificaciones',
                'suscripcion' => 'Suscripción',
                'creditos-ia' => 'Créditos de IA',
                'perfil' => 'Mi perfil',
                'aviso-privacidad' => 'Aviso de privacidad',
            ];
            abort_unless(array_key_exists($section, $sections), 404);
            return view('teacher.coming-soon', [
                'title' => $sections[$section],
            ]);
        })->name('teacher.coming-soon');
    });

    Route::middleware('role:student')->group(function (): void {
        Route::get('grupos/unirse', [StudentExamController::class, 'joinForm'])->name('student.groups.join.form');
        Route::post('grupos/unirse', [StudentExamController::class, 'join'])->name('student.groups.join');
        Route::get('mis-examenes', [StudentExamController::class, 'index'])->name('student.exams.index');

        Route::post('examenes/{exam}/iniciar', [AttemptController::class, 'start'])->name('attempts.start');
    });

    Route::get('intentos/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::post('intentos/{attempt}/responder', [AttemptController::class, 'answer'])->name('attempts.answer');
    Route::post('intentos/{attempt}/terminar', [AttemptController::class, 'finish'])->name('attempts.finish');
    Route::get('intentos/{attempt}/resultado', [AttemptController::class, 'result'])->name('attempts.result');
});
