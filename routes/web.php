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

use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\AdminInvitationCodeController;
use App\Http\Controllers\AdminTeacherController;
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
use App\Http\Controllers\QuestionFileController;
use App\Http\Controllers\StudentExamController;
use App\Http\Controllers\StudentGradeController;
use App\Http\Controllers\StudentGroupController;
use App\Http\Controllers\TeacherAiCreditsController;
use App\Http\Controllers\TeacherProfileController;
use App\Http\Controllers\TeacherReviewController;
use App\Http\Controllers\TeacherSubscriptionController;
use App\Http\Controllers\TeacherVerificationController;
use App\Http\Controllers\WebPrivacyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('aviso-privacidad', [WebPrivacyController::class, 'show'])->name('privacy.show.public');

Route::middleware('guest')->group(function (): void {
    Route::get('register/docente', [RegisterController::class, 'showTeacherForm'])->name('auth.register.teacher.show');
    Route::post('register/docente', [RegisterController::class, 'storeTeacher'])
        ->middleware('throttle:10,1')
        ->name('auth.register.teacher.store');
    Route::get('register/alumno', [RegisterController::class, 'showStudentForm'])->name('auth.register.student.show');
    Route::post('register/alumno', [RegisterController::class, 'storeStudent'])->name('auth.register.student.store');

    Route::get('login', [LoginController::class, 'showLoginForm'])->name('auth.login.show');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('auth.login.store');

    Route::get('verificar-codigo/{user}', [LoginController::class, 'showTwoFactorForm'])->name('auth.verify-2fa.show');
    Route::post('verificar-codigo', [LoginController::class, 'verifyTwoFactor'])->middleware('throttle:10,1')->name('auth.verify-2fa.store');
    Route::post('verificar-codigo/reenviar', [LoginController::class, 'resendTwoFactor'])->middleware('throttle:10,1')->name('auth.verify-2fa.resend');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'logout'])->name('auth.logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Imagen y PDF de las preguntas en almacenamiento privado (RF-BAN-012, RF-BAN-013, ACT-WEB-026)
    Route::get('preguntas/{question}/archivo/{kind}', QuestionFileController::class)
        ->whereNumber('question')
        ->whereIn('kind', ['imagen', 'pdf'])
        ->name('questions.file');

    // Fotografía de perfil en almacenamiento privado (RF-AUT-008, ACT-WEB-038)
    Route::get('usuarios/{user}/foto', ProfilePhotoController::class)
        ->whereNumber('user')
        ->name('users.photo');

    // Perfil del alumno y del superadministrador (RF-AUT-013, ACT-WEB-050)
    Route::get('mi-perfil', AccountProfileController::class)
        ->middleware('role:student,super_admin')
        ->name('account.profile');
    Route::post('mi-perfil/foto', [AccountProfileController::class, 'updatePhoto'])
        ->middleware('role:student')
        ->name('account.profile.photo');

    Route::middleware('role:teacher')->group(function (): void {
        Route::get('docente/verificacion', [TeacherVerificationController::class, 'show'])
            ->name('teacher.verification.status');
        Route::post('docente/verificacion/reenviar', [TeacherVerificationController::class, 'resubmit'])
            ->middleware('throttle:10,1')
            ->name('teacher.verification.resubmit');
        Route::post('docente/verificacion/canjear-codigo', [TeacherVerificationController::class, 'redeemCode'])
            ->middleware('throttle:10,1')
            ->name('teacher.verification.redeem-code');
    });

    Route::middleware(['role:teacher', 'teacher.verified'])->group(function (): void {
        Route::get('docente/suscripcion', [TeacherSubscriptionController::class, 'index'])
            ->name('teacher.subscription.index');
        Route::get('docente/suscripcion/checkout/{package}', [TeacherSubscriptionController::class, 'checkout'])
            ->whereNumber('package')
            ->name('teacher.subscription.checkout');
        Route::post('docente/suscripcion/checkout/{package}', [TeacherSubscriptionController::class, 'processPayment'])
            ->whereNumber('package')
            ->middleware('throttle:15,1')
            ->name('teacher.subscription.pay');
        Route::post('docente/suscripcion/actualizar/{package}', [TeacherSubscriptionController::class, 'upgrade'])
            ->whereNumber('package')
            ->middleware('throttle:15,1')
            ->name('teacher.subscription.upgrade');
        Route::post('docente/suscripcion/cancelar-renovacion', [TeacherSubscriptionController::class, 'cancelRenewal'])
            ->middleware('throttle:15,1')
            ->name('teacher.subscription.cancel-renewal');
        Route::post('docente/suscripcion/prueba-gratuita', [TeacherSubscriptionController::class, 'startFreeTrial'])
            ->middleware('throttle:15,1')
            ->name('teacher.subscription.free-trial');

        Route::middleware('teacher.plan_onboarded')->group(function (): void {
            Route::resource('groups', GroupController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::post('groups/{group}/cerrar', [GroupController::class, 'close'])->name('groups.close');
        Route::post('groups/{group}/reabrir', [GroupController::class, 'reopen'])->name('groups.reopen');
        Route::delete('groups/{group}/alumnos/{student}', [GroupController::class, 'removeStudent'])
            ->whereNumber('student')
            ->name('groups.students.remove');
        Route::get('groups/{group}/periodos', [EvaluationPeriodController::class, 'index'])->name('groups.periods.index');
        Route::post('groups/{group}/periodos', [EvaluationPeriodController::class, 'store'])->name('groups.periods.store');
        Route::get('groups/{group}/periodos/{period}/sabana', [EvaluationPeriodController::class, 'gradebook'])->name('groups.periods.gradebook');
        Route::post('groups/{group}/periodos/{period}/sabana', [EvaluationPeriodController::class, 'saveGradebook'])->name('groups.periods.gradebook.save');
        Route::get('groups/{group}/periodos/{period}/sabana/export', [EvaluationPeriodController::class, 'exportGradebook'])->name('groups.periods.gradebook.export');

        Route::get('questions/template/csv', [QuestionController::class, 'downloadTemplate'])->name('questions.template.csv');
        Route::get('questions/template/xlsx', [QuestionController::class, 'downloadExcelTemplate'])->name('questions.template.xlsx');
        Route::post('questions/import/preview', [QuestionController::class, 'previewImport'])->name('questions.import.preview');
        Route::post('questions/import', [QuestionController::class, 'import'])->name('questions.import');
        Route::get('questions/batch/create', [QuestionController::class, 'batchCreate'])->name('questions.batch.create');
        Route::post('questions/batch', [QuestionController::class, 'batchStore'])->name('questions.batch.store');
        Route::post('questions/subjects/merge', [QuestionController::class, 'mergeSubjects'])->name('questions.subjects.merge');
        Route::get('questions/subjects/suggestions', [QuestionController::class, 'suggestSubjects'])->name('questions.subjects.suggestions');
        Route::post('questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
        Route::post('questions/{question}/parafrasis', [QuestionController::class, 'storeParaphrase'])->name('questions.paraphrase.store');
        Route::delete('questions/{question}/parafrasis/{paraphrase}', [QuestionController::class, 'destroyParaphrase'])
            ->whereNumber('paraphrase')
            ->name('questions.paraphrase.destroy');
        Route::resource('questions', QuestionController::class);

        Route::resource('exams', ExamController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
            ->whereNumber('exam');
        Route::get('exams/{exam}/armar', [ExamController::class, 'assemble'])
            ->whereNumber('exam')
            ->name('exams.assemble');
        Route::post('exams/{exam}/apartados', [ExamController::class, 'storeSection'])
            ->whereNumber('exam')
            ->name('exams.sections.store');
        Route::put('exams/{exam}/apartados/{examSection}', [ExamController::class, 'updateSection'])
            ->whereNumber(['exam', 'examSection'])
            ->name('exams.sections.update');
        Route::delete('exams/{exam}/apartados/{examSection}', [ExamController::class, 'destroySection'])
            ->whereNumber(['exam', 'examSection'])
            ->name('exams.sections.destroy');
        Route::post('exams/{exam}/propuesta', [ExamController::class, 'generateSectionProposal'])
            ->whereNumber('exam')
            ->name('exams.proposals.generate');
        Route::post('exams/{exam}/propuesta/aceptar', [ExamController::class, 'acceptSectionProposal'])
            ->whereNumber('exam')
            ->name('exams.proposals.accept');
        Route::post('exams/{exam}/propuesta/descartar', [ExamController::class, 'discardSectionProposal'])
            ->whereNumber('exam')
            ->name('exams.proposals.discard');
        Route::post('exams/{exam}/reactivos-manuales', [ExamController::class, 'storeManualQuestions'])
            ->whereNumber('exam')
            ->name('exams.questions.manual.store');
        Route::post('exams/{exam}/reactivos/{examQuestion}/nuevo-sorteo', [ExamController::class, 'reshuffleQuestion'])
            ->whereNumber(['exam', 'examQuestion'])
            ->name('exams.questions.reshuffle');
        Route::delete('exams/{exam}/reactivos/{examQuestion}', [ExamController::class, 'destroyQuestion'])
            ->whereNumber(['exam', 'examQuestion'])
            ->name('exams.questions.destroy');
        Route::post('exams/{exam}/publicar', [ExamController::class, 'publish'])
            ->whereNumber('exam')
            ->name('exams.publish');
        Route::post('exams/{exam}/despublicar', [ExamController::class, 'unpublish'])
            ->whereNumber('exam')
            ->name('exams.unpublish');
        Route::post('exams/{exam}/cerrar', [ExamController::class, 'close'])
            ->whereNumber('exam')
            ->name('exams.close');
        Route::patch('exams/{exam}/visibilidad-resultados', [ExamController::class, 'releaseResults'])
            ->whereNumber('exam')
            ->name('exams.results.release');
        Route::get('exams/{exam}/configuracion', [ExamController::class, 'configuration'])
            ->whereNumber('exam')
            ->name('exams.configuration');
        Route::put('exams/{exam}/configuracion', [ExamController::class, 'updateConfiguration'])
            ->whereNumber('exam')
            ->name('exams.configuration.update');
        Route::get('exams/{exam}/revisar', [ExamController::class, 'review'])
            ->whereNumber('exam')
            ->name('exams.review');
        Route::get('exams/{exam}/monitoreo', [ExamMonitorController::class, 'show'])
            ->whereNumber('exam')
            ->name('exams.monitor');
        Route::get('exams/{exam}/revision', [TeacherReviewController::class, 'index'])
            ->whereNumber('exam')
            ->name('exams.revision.index');
        Route::get('exams/{exam}/revision/{attempt}', [TeacherReviewController::class, 'show'])
            ->whereNumber(['exam', 'attempt'])
            ->name('exams.revision.show');
        Route::post('exams/{exam}/revision/{attempt}', [TeacherReviewController::class, 'update'])
            ->whereNumber(['exam', 'attempt'])
            ->name('exams.revision.update');
        Route::post('exams/{exam}/revision/{attempt}/respuestas/{answer}/evaluar-ia', [TeacherReviewController::class, 'evaluateWithAi'])
            ->whereNumber(['exam', 'attempt', 'answer'])
            ->middleware('throttle:30,1')
            ->name('exams.revision.evaluate-ai');
        Route::post('exams/{exam}/revision/{attempt}/evaluar-ia-lote', [TeacherReviewController::class, 'evaluateAttemptWithAi'])
            ->whereNumber(['exam', 'attempt'])
            ->middleware('throttle:10,1')
            ->name('exams.revision.evaluate-ai-batch');

        // Módulos funcionales docentes (sustituyen coming-soon)
        Route::get('docente/revision', [TeacherReviewController::class, 'globalIndex'])->name('teacher.reviews.index');
        Route::get('docente/calificaciones', [EvaluationPeriodController::class, 'hub'])->name('teacher.gradebook.hub');
        Route::get('docente/creditos-ia', [TeacherAiCreditsController::class, 'index'])->name('teacher.ai-credits.index');
        Route::get('perfil', [TeacherProfileController::class, 'show'])->name('teacher.profile.index');
        Route::put('perfil', [TeacherProfileController::class, 'update'])->name('teacher.profile.update');
        });
    });

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('docentes', [AdminTeacherController::class, 'index'])->name('teachers.index');
        Route::post('docentes/{teacher}/toggle', [AdminTeacherController::class, 'toggleStatus'])
            ->whereNumber('teacher')
            ->name('teachers.toggle');
        Route::post('docentes/{teacher}/assign', [AdminTeacherController::class, 'assignPackage'])
            ->whereNumber('teacher')
            ->name('teachers.assign');
        Route::get('docentes/{teacher}/verificacion/documento', [AdminTeacherController::class, 'document'])
            ->whereNumber('teacher')
            ->name('teachers.verification.document');
        Route::post('docentes/{teacher}/verificacion/aprobar', [AdminTeacherController::class, 'approveVerification'])
            ->whereNumber('teacher')
            ->name('teachers.verification.approve');
        Route::post('docentes/{teacher}/verificacion/rechazar', [AdminTeacherController::class, 'rejectVerification'])
            ->whereNumber('teacher')
            ->name('teachers.verification.reject');

        Route::get('invitaciones', [AdminInvitationCodeController::class, 'index'])->name('invitations.index');
        Route::post('invitaciones', [AdminInvitationCodeController::class, 'store'])->name('invitations.store');
        Route::post('invitaciones/{invitationCode}/toggle', [AdminInvitationCodeController::class, 'toggle'])
            ->whereNumber('invitationCode')
            ->name('invitations.toggle');
        Route::post('invitaciones/{invitationCode}/enviar', [AdminInvitationCodeController::class, 'sendEmail'])
            ->whereNumber('invitationCode')
            ->middleware('throttle:20,1')
            ->name('invitations.send-email');
    });

    Route::middleware('role:student')->group(function (): void {
        Route::get('mis-grupos', [StudentGroupController::class, 'index'])->name('student.groups.index');
        Route::delete('mis-grupos/{group}/abandonar', [StudentGroupController::class, 'leave'])->name('student.groups.leave');
        Route::get('grupos/unirse', [StudentExamController::class, 'joinForm'])->name('student.groups.join.form');
        Route::post('grupos/unirse', [StudentExamController::class, 'join'])->name('student.groups.join');

        Route::get('mis-examenes', [StudentExamController::class, 'index'])->name('student.exams.index');
        Route::get('examenes/{exam}/instrucciones', [StudentExamController::class, 'instructions'])->name('student.exams.instructions');
        Route::post('examenes/{exam}/iniciar', [AttemptController::class, 'start'])->name('attempts.start');

        Route::get('mis-calificaciones', [StudentGradeController::class, 'index'])->name('student.grades.index');
        Route::get('mis-calificaciones/{group}', [StudentGradeController::class, 'show'])->name('student.grades.show');

        Route::get('intentos/{attempt}/revision', [AttemptController::class, 'review'])->name('attempts.review');
    });

    Route::get('intentos/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::post('intentos/{attempt}/responder', [AttemptController::class, 'answer'])->name('attempts.answer');
    // Guardado automático del borrador de la pregunta activa (RF-EXA-040, RF-EXA-041)
    Route::post('intentos/{attempt}/borrador', [AttemptController::class, 'draft'])
        ->whereNumber('attempt')
        ->middleware('throttle:120,1')
        ->name('attempts.draft');
    Route::post('intentos/{attempt}/incidente', [AttemptController::class, 'incident'])
        ->whereNumber('attempt')
        ->middleware('throttle:30,1')
        ->name('attempts.incident');
    Route::post('intentos/{attempt}/terminar', [AttemptController::class, 'finish'])->name('attempts.finish');
    Route::get('intentos/{attempt}/resultado', [AttemptController::class, 'result'])->name('attempts.result');
});
