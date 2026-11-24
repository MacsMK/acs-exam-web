<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherVerificationFlowTest.php
 * Ubicación:      tests/Feature/Auth/TeacherVerificationFlowTest.php
 * Requerimiento:  RF-AUT-001, RW-03, RW-05, RNF-SEG-007, RNF-SEG-008, ADR-057
 *                 (Verificación Híbrida de Identidad Docente y Purga LFPDPPP)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Certificación integral del flujo de acreditación híbrida de
 *                 identidad docente por vía documental: registro abierto con
 *                 PDF/JPG, validación server-side de archivos, detección de
 *                 correo institucional, verificador enchufable SEP manual,
 *                 confinamiento post-2FA web y API v1, acceso endurecido al
 *                 comprobante privado, dictamen administrativo con purga
 *                 física LFPDPPP, reenvío tras rechazo y protección contra
 *                 asignación masiva.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\PrivacyNotice;
use App\Models\TeacherVerification;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Verification\ManualProfessionalLicenseVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Pruebas Feature del ciclo completo de verificación documental docente.
 *
 * @package Tests\Feature\Auth
 */
class TeacherVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private PrivacyNotice $privacyNotice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privacyNotice = PrivacyNotice::create([
            'version' => '2.0',
            'content' => 'Aviso de privacidad vigente con cláusula LFPDPPP de purga documental.',
            'published_at' => now()->subMinute(),
        ]);
    }

    public function test_local_demo_teacher_can_register_with_an_ordinary_email_and_verify_on_screen(): void
    {
        $this->app->instance('env', 'local');
        config(['mail.default' => 'log', 'auth.two_factor_demo' => true]);
        Storage::fake('local');
        Mail::shouldReceive('raw')->never();

        $email = 'docente.demo@example.com';
        $csrfToken = Str::random(40);
        $response = $this->withSession(['_token' => $csrfToken])
            ->post(route('auth.register.teacher.store'), $this->openRegistrationPayload([
                'email' => $email,
                '_token' => $csrfToken,
            ]));
        $response->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', $email)->firstOrFail();
        $verificationUrl = route('auth.verify-2fa.show', ['user' => $user->id]);
        $response->assertRedirect($verificationUrl);
        $this->assertSame('demo', $user->two_factor_delivery);

        $this->get($verificationUrl)->assertOk()
            ->assertSee('Modo demo / Entorno de pruebas')
            ->assertSee($user->two_factor_code);

        $this->post(route('auth.verify-2fa.store'), [
            'user_id' => $user->id,
            'code' => $user->two_factor_code,
            '_token' => $csrfToken,
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_open_web_registration_with_pdf_creates_pending_verification_stores_private_document_and_runs_sep_verifier(): void
    {
        Storage::fake('local');
        Mail::fake();

        $response = $this->post(route('auth.register.teacher.store'), $this->openRegistrationPayload([
            'first_name' => 'Elena',
            'last_name' => 'Morales Vega',
            'email' => 'elena.morales@unam.edu.mx',
            'institution_name' => 'Universidad Nacional Autónoma de México',
            'educational_level' => 'superior',
            'professional_license' => '12345678',
            'credential_type' => 'institutional_id',
            'verification_document' => UploadedFile::fake()->create('credencial.pdf', 200, 'application/pdf'),
        ]));

        $user = User::where('email', 'elena.morales@unam.edu.mx')->firstOrFail();

        $response->assertRedirect(route('auth.verify-2fa.show', ['user' => $user->id]));
        $response->assertSessionHas('pending_2fa_user_id', $user->id);
        $response->assertSessionHasNoErrors();

        $this->assertSame('teacher', $user->role);
        $this->assertSame(TeacherVerification::STATUS_PENDING, $user->teacher_verification_status);
        $this->assertTrue($user->isPendingTeacherVerification());
        $this->assertFalse($user->isVerifiedTeacher());

        $verification = TeacherVerification::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(TeacherVerification::STATUS_PENDING, $verification->status);
        $this->assertSame(TeacherVerification::METHOD_DOCUMENT_REVIEW, $verification->verification_method);
        $this->assertSame('Universidad Nacional Autónoma de México', $verification->institution_name);
        $this->assertSame('superior', $verification->educational_level);
        $this->assertSame('12345678', $verification->professional_license);
        $this->assertSame('institutional_id', $verification->credential_type);
        $this->assertTrue($verification->is_institutional_email);
        $this->assertNull($verification->document_purged_at);
        $this->assertNull($verification->invitation_code_id);

        // Almacenamiento privado en disco local bajo teacher-verifications/{user_id}/...
        $this->assertNotNull($verification->document_path);
        $this->assertStringStartsWith("teacher-verifications/{$user->id}/", $verification->document_path);
        $this->assertStringEndsWith('.pdf', $verification->document_path);
        Storage::disk('local')->assertExists($verification->document_path);

        // Ejecución del driver manual de cédula SEP (ADR-057)
        $this->assertIsArray($verification->license_check_payload);
        $this->assertFalse($verification->license_check_payload['verified']);
        $this->assertSame(
            ManualProfessionalLicenseVerifier::STATUS_MANUAL_REVIEW,
            $verification->license_check_payload['status'],
        );
        $this->assertSame(
            ManualProfessionalLicenseVerifier::PROVIDER_NAME,
            $verification->license_check_payload['provider'],
        );
        $this->assertSame('12345678', $verification->license_check_payload['details']['license_number']);
        $this->assertSame('Elena Morales Vega', $verification->license_check_payload['details']['declared_name']);
        $this->assertSame(
            ManualProfessionalLicenseVerifier::SEP_LOOKUP_URL,
            $verification->license_check_payload['details']['lookup_url'],
        );

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $user->id,
            'action' => 'teacher.verification_submitted',
            'auditable_type' => TeacherVerification::class,
            'auditable_id' => $verification->id,
        ]);
    }

    public function test_open_web_registration_with_jpg_and_gmail_marks_non_institutional_and_validates_7_digit_license(): void
    {
        Storage::fake('local');
        Mail::fake();

        $response = $this->post(route('auth.register.teacher.store'), $this->openRegistrationPayload([
            'first_name' => 'Carlos',
            'last_name' => 'Ríos Méndez',
            'email' => 'carlos.rios@gmail.com',
            'institution_name' => 'Preparatoria Independiente Sur',
            'educational_level' => 'media_superior',
            'professional_license' => '7654321',
            'credential_type' => 'employment_letter',
            'verification_document' => UploadedFile::fake()->image('credencial.jpg', 320, 200),
        ]));

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'carlos.rios@gmail.com')->firstOrFail();
        $verification = TeacherVerification::where('user_id', $user->id)->firstOrFail();

        $this->assertSame(TeacherVerification::STATUS_PENDING, $user->teacher_verification_status);
        $this->assertFalse($verification->is_institutional_email);
        $this->assertSame('7654321', $verification->professional_license);
        $this->assertSame(
            ManualProfessionalLicenseVerifier::STATUS_MANUAL_REVIEW,
            $verification->license_check_payload['status'] ?? null,
        );
        $this->assertSame('7654321', $verification->license_check_payload['details']['license_number'] ?? null);

        $this->assertStringStartsWith("teacher-verifications/{$user->id}/", (string) $verification->document_path);
        $this->assertStringEndsWith('.jpg', (string) $verification->document_path);
        Storage::disk('local')->assertExists((string) $verification->document_path);
    }

    public function test_server_side_file_validation_rejects_disallowed_types_and_oversized_files(): void
    {
        Storage::fake('local');
        Mail::fake();

        $invalidFiles = [
            'php' => UploadedFile::fake()->create('shell.php', 50, 'application/x-php'),
            'txt' => UploadedFile::fake()->create('notas.txt', 20, 'text/plain'),
            'exe' => UploadedFile::fake()->create('payload.exe', 100, 'application/octet-stream'),
            'oversized' => UploadedFile::fake()->create('enorme.pdf', 5121, 'application/pdf'),
        ];

        foreach ($invalidFiles as $case => $file) {
            $email = "rechazado.{$case}@example.test";

            $response = $this->post(route('auth.register.teacher.store'), $this->openRegistrationPayload([
                'email' => $email,
                'verification_document' => $file,
            ]));

            $response->assertSessionHasErrors('verification_document');
            $this->assertDatabaseMissing('acs_users', ['email' => $email]);
        }

        $this->assertDatabaseCount('acs_teacher_verifications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_post_2fa_confinement_redirects_pending_and_rejected_teachers_while_allowing_approved_and_students(): void
    {
        Storage::fake('local');
        Mail::fake();

        // 1. Docente pendiente completando 2FA real queda confinado a teacher.verification.status
        $pendingTeacher = $this->registerPendingTeacherViaHttp('pendiente.2fa@escuela.edu.mx');
        $pendingCode = (string) $pendingTeacher->fresh()->two_factor_code;

        $verifyPending = $this->withSession(['pending_2fa_user_id' => $pendingTeacher->id])
            ->post(route('auth.verify-2fa.store'), [
                'user_id' => $pendingTeacher->id,
                'code' => $pendingCode,
            ]);

        $verifyPending->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($pendingTeacher);

        foreach (['/dashboard', '/groups', '/questions', '/exams', '/perfil'] as $restrictedPath) {
            $this->get($restrictedPath)->assertRedirect(route('teacher.verification.status'));
        }

        $this->get(route('teacher.verification.status'))
            ->assertOk()
            ->assertSee($pendingTeacher->teacherVerification->institution_name);

        // 2. Docente rechazado completando login + 2FA también queda confinado a teacher.verification.status
        $this->post(route('auth.logout'));
        $this->app['auth']->forgetGuards();

        $rejectedTeacher = User::factory()->rejectedVerification()->create([
            'email' => 'rechazado.2fa@gmail.com',
        ]);
        TeacherVerification::create([
            'user_id' => $rejectedTeacher->id,
            'status' => TeacherVerification::STATUS_REJECTED,
            'verification_method' => TeacherVerification::METHOD_DOCUMENT_REVIEW,
            'institution_name' => 'Escuela Modelo',
            'credential_type' => 'institutional_id',
            'is_institutional_email' => false,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now()->subMinutes(10),
            'rejection_reason' => 'La fotografía del documento es borrosa e ilegible.',
        ]);

        app(AuthService::class)->issueTwoFactorCode($rejectedTeacher);
        $rejectedCode = (string) $rejectedTeacher->fresh()->two_factor_code;

        $this->withSession(['pending_2fa_user_id' => $rejectedTeacher->id])
            ->post(route('auth.verify-2fa.store'), [
                'user_id' => $rejectedTeacher->id,
                'code' => $rejectedCode,
            ])
            ->assertRedirect(route('dashboard'));

        foreach (['/dashboard', '/groups', '/questions', '/exams', '/perfil'] as $restrictedPath) {
            $this->get($restrictedPath)->assertRedirect(route('teacher.verification.status'));
        }

        $this->get(route('teacher.verification.status'))
            ->assertOk()
            ->assertSee('La fotografía del documento es borrosa e ilegible.');

        // 3. Docente aprobado accede normalmente y es redirigido al dashboard si entra al estatus de verificación
        $this->app['auth']->forgetGuards();
        $approvedTeacher = User::factory()->teacher()->create();

        foreach (['/dashboard', '/groups', '/questions', '/exams', '/perfil'] as $teacherPath) {
            $this->actingAs($approvedTeacher)->get($teacherPath)->assertOk();
        }
        $this->actingAs($approvedTeacher)
            ->get(route('teacher.verification.status'))
            ->assertRedirect(route('dashboard'));

        // 4. Alumno accede normalmente a sus módulos
        $this->app['auth']->forgetGuards();
        $student = User::factory()->student()->create();
        $this->actingAs($student)->get('/dashboard')->assertOk();
        $this->actingAs($student)->get(route('student.groups.index'))->assertOk();
    }

    public function test_api_v1_ensure_teacher_is_verified_blocks_pending_and_rejected_teachers_while_allowing_me_and_students(): void
    {
        $pendingTeacher = User::factory()->pendingVerification()->create();
        $rejectedTeacher = User::factory()->rejectedVerification()->create();
        $approvedTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $pendingToken = $pendingTeacher->createToken('test-mobile')->plainTextToken;
        $rejectedToken = $rejectedTeacher->createToken('test-mobile')->plainTextToken;
        $approvedToken = $approvedTeacher->createToken('test-mobile')->plainTextToken;
        $studentToken = $student->createToken('test-mobile')->plainTextToken;

        // Docente pending: /api/v1/auth/me permitido exponiendo teacher_verification_status
        $this->withFreshToken($pendingToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.teacher_verification_status', TeacherVerification::STATUS_PENDING);

        // Docente pending: rutas de negocio bloqueadas con 403 y sobre uniforme ApiResponse
        foreach (['/api/v1/groups', '/api/v1/exams', '/api/v1/grades'] as $endpoint) {
            $this->withFreshToken($pendingToken)
                ->getJson($endpoint)
                ->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('code', 403)
                ->assertJsonPath('data', null)
                ->assertJsonPath('errors.verification_status', TeacherVerification::STATUS_PENDING);
        }

        // Docente rejected también bloqueado con verification_status = rejected y mensaje específico
        $rejectedApiResponse = $this->withFreshToken($rejectedToken)
            ->getJson('/api/v1/groups')
            ->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 403)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors.verification_status', TeacherVerification::STATUS_REJECTED);

        $this->assertStringContainsString('rechazada', mb_strtolower((string) $rejectedApiResponse->json('message')));

        // Docente aprobado accede con normalidad
        foreach (['/api/v1/groups', '/api/v1/exams', '/api/v1/grades'] as $endpoint) {
            $this->withFreshToken($approvedToken)
                ->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('status', 'success');
        }

        // Alumno no se ve afectado y recibe 200 OK
        foreach (['/api/v1/auth/me', '/api/v1/groups', '/api/v1/exams', '/api/v1/grades'] as $endpoint) {
            $this->withFreshToken($studentToken)
                ->getJson($endpoint)
                ->assertOk()
                ->assertJsonPath('status', 'success');
        }
    }

    public function test_verification_document_endpoint_enforces_super_admin_authorization_and_security_headers(): void
    {
        Storage::fake('local');
        Mail::fake();

        $pendingTeacher = $this->registerPendingTeacherViaHttp(
            'docente.comprobante@ipn.mx',
            UploadedFile::fake()->createWithContent('credencial_docente.pdf', "%PDF-1.4\n%comprobante-prueba\n"),
        );

        $superAdmin = $this->createSuperAdmin();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $documentUrl = route('admin.teachers.verification.document', $pendingTeacher);

        // 1. Invitado -> 302 a /login
        $this->get($documentUrl)->assertRedirect('/login');

        // 2. Otro docente -> 403
        $this->actingAs($otherTeacher)->get($documentUrl)->assertForbidden();

        // 3. El propio docente pendiente -> 403
        $this->actingAs($pendingTeacher)->get($documentUrl)->assertForbidden();

        // 4. Alumno -> 403
        $this->actingAs($student)->get($documentUrl)->assertForbidden();

        // 5. Super Admin -> 200 OK con cabeceras de endurecimiento RNF-SEG-008 y bitácora de acceso
        $adminResponse = $this->actingAs($superAdmin)->get($documentUrl);
        $adminResponse->assertOk();
        $adminResponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $adminResponse->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $adminResponse->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'self'; sandbox");
        $adminResponse->assertHeader('Content-Type', 'application/pdf');

        $cacheControl = (string) $adminResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);

        $disposition = (string) $adminResponse->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline;', $disposition);

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'teacher.verification_document_viewed',
            'auditable_type' => TeacherVerification::class,
            'auditable_id' => $pendingTeacher->teacherVerification()->value('id'),
        ]);
    }

    public function test_super_admin_approval_purges_document_audits_notifies_by_email_and_grants_immediate_access(): void
    {
        Storage::fake('local');
        config(['mail.default' => 'array']);

        $pendingTeacher = $this->registerPendingTeacherViaHttp('docente.aprobar@tecnm.mx');
        $verification = $pendingTeacher->teacherVerification()->firstOrFail();
        $originalPath = (string) $verification->document_path;

        Storage::disk('local')->assertExists($originalPath);

        $superAdmin = $this->createSuperAdmin();

        // Se utiliza el transporte en memoria 'array' (phpunit.xml) porque
        // TeacherVerificationService::sendDecisionEmail() envía vía Mail::raw().
        $this->flushArrayMailTransport();

        $approveResponse = $this->actingAs($superAdmin)
            ->post(route('admin.teachers.verification.approve', $pendingTeacher));

        $approveResponse->assertRedirect();
        $approveResponse->assertSessionHas('status');

        $pendingTeacher->refresh();
        $verification->refresh();

        $this->assertSame(TeacherVerification::STATUS_APPROVED, $pendingTeacher->teacher_verification_status);
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $verification->status);
        $this->assertNull($verification->document_path);
        $this->assertNull($verification->document_original_name);
        $this->assertNull($verification->document_mime_type);
        $this->assertNotNull($verification->document_purged_at);
        $this->assertSame($superAdmin->id, $verification->reviewed_by);
        $this->assertNotNull($verification->reviewed_at);

        // Purga física LFPDPPP verificada en disco privado
        Storage::disk('local')->assertMissing($originalPath);

        // Auditoría inmutable
        $auditLog = AuditLog::where('action', 'teacher.verification_approved')
            ->where('auditable_type', TeacherVerification::class)
            ->where('auditable_id', $verification->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($superAdmin->id, $auditLog->user_id);
        $this->assertTrue((bool) ($auditLog->new_values['document_purged'] ?? false));
        $this->assertSame($pendingTeacher->id, $auditLog->new_values['target_teacher_id'] ?? null);

        // Guardia de transición de estado: no se puede volver a dictaminar (aprobar ni rechazar) un expediente ya aprobado
        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.verification.reject', $pendingTeacher), [
                'rejection_reason' => 'Intento de revertir una cuenta ya aprobada.',
            ])
            ->assertSessionHasErrors('verification');
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $pendingTeacher->fresh()->teacher_verification_status);

        // Notificación por correo al docente aprobado
        $sentEmails = $this->capturedEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame([$pendingTeacher->email], $this->recipientEmails($sentEmails[0]));
        $this->assertSame('Cuenta docente aprobada - A-Code Solutions', $sentEmails[0]->getSubject());
        $this->assertStringContainsString('LFPDPPP', (string) $sentEmails[0]->getTextBody());

        // Intento posterior de descargar el comprobante purgado retorna 404
        $this->actingAs($superAdmin)
            ->get(route('admin.teachers.verification.document', $pendingTeacher))
            ->assertNotFound();

        // El docente recién aprobado ve el overlay bloqueante en /dashboard y /groups redirige a dashboard (ADR-060)
        $this->app['auth']->forgetGuards();
        $this->actingAs($pendingTeacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false)
            ->assertSee('Confirmar prueba gratuita');
        $this->actingAs($pendingTeacher)
            ->get('/groups')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($pendingTeacher)
            ->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($pendingTeacher->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($pendingTeacher->fresh())->get('/groups')->assertOk();
    }

    public function test_super_admin_rejection_requires_reason_purges_document_notifies_and_allows_resubmission_then_approval(): void
    {
        Storage::fake('local');
        config(['mail.default' => 'array']);

        $teacher = $this->registerPendingTeacherViaHttp('docente.rechazo@gmail.com');
        $verification = $teacher->teacherVerification()->firstOrFail();
        $firstPath = (string) $verification->document_path;

        Storage::disk('local')->assertExists($firstPath);

        $superAdmin = $this->createSuperAdmin();

        // 1. Motivo menor a 10 caracteres es rechazado con error de validación y conserva estado/archivo
        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.verification.reject', $teacher), [
                'rejection_reason' => 'Muy corto',
            ])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(TeacherVerification::STATUS_PENDING, $teacher->fresh()->teacher_verification_status);
        Storage::disk('local')->assertExists($firstPath);

        // 2. Rechazo con motivo normativo válido
        $reason = 'El comprobante adjunto se encuentra ilegible y carece de vigencia institucional.';
        $this->flushArrayMailTransport();

        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.verification.reject', $teacher), [
                'rejection_reason' => $reason,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $teacher->refresh();
        $verification->refresh();

        $this->assertSame(TeacherVerification::STATUS_REJECTED, $teacher->teacher_verification_status);
        $this->assertSame(TeacherVerification::STATUS_REJECTED, $verification->status);
        $this->assertSame($reason, $verification->rejection_reason);
        $this->assertNull($verification->document_path);
        $this->assertNull($verification->document_original_name);
        $this->assertNull($verification->document_mime_type);
        $this->assertNotNull($verification->document_purged_at);
        $this->assertSame($superAdmin->id, $verification->reviewed_by);
        $this->assertNotNull($verification->reviewed_at);

        // Purga física del primer comprobante y 404 al intentar consultarlo
        Storage::disk('local')->assertMissing($firstPath);
        $this->actingAs($superAdmin)
            ->get(route('admin.teachers.verification.document', $teacher))
            ->assertNotFound();

        // Auditoría de rechazo
        $rejectAudit = AuditLog::where('action', 'teacher.verification_rejected')
            ->where('auditable_type', TeacherVerification::class)
            ->where('auditable_id', $verification->id)
            ->first();

        $this->assertNotNull($rejectAudit);
        $this->assertSame($superAdmin->id, $rejectAudit->user_id);
        $this->assertSame($reason, $rejectAudit->new_values['rejection_reason'] ?? null);
        $this->assertTrue((bool) ($rejectAudit->new_values['document_purged'] ?? false));

        // Correo de rechazo al docente con el motivo exacto
        $sentEmails = $this->capturedEmails();
        $this->assertCount(1, $sentEmails);
        $this->assertSame([$teacher->email], $this->recipientEmails($sentEmails[0]));
        $this->assertSame('Actualización sobre tu acreditación docente - A-Code Solutions', $sentEmails[0]->getSubject());
        $this->assertStringContainsString($reason, (string) $sentEmails[0]->getTextBody());

        // 3. Reenvío de nuevo comprobante por el docente vía POST /docente/verificacion/reenviar
        $this->app['auth']->forgetGuards();

        $resubmitResponse = $this->actingAs($teacher)
            ->post(route('teacher.verification.resubmit'), [
                'institution_name' => 'Instituto Tecnológico Superior de Puebla',
                'educational_level' => 'superior',
                'professional_license' => '87654321',
                'credential_type' => 'official_schedule',
                'verification_document' => UploadedFile::fake()->image('horario_sellado.jpg', 400, 300),
            ]);

        $resubmitResponse->assertRedirect(route('teacher.verification.status'));
        $resubmitResponse->assertSessionHasNoErrors();

        $teacher->refresh();
        $verification->refresh();
        $secondPath = (string) $verification->document_path;

        $this->assertSame(TeacherVerification::STATUS_PENDING, $teacher->teacher_verification_status);
        $this->assertSame(TeacherVerification::STATUS_PENDING, $verification->status);
        $this->assertSame(1, $verification->resubmission_count);
        $this->assertNull($verification->rejection_reason);
        $this->assertNull($verification->reviewed_by);
        $this->assertNull($verification->reviewed_at);
        $this->assertNull($verification->document_purged_at);
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $teacher->id,
            'action' => 'teacher.verification_resubmitted',
            'auditable_type' => TeacherVerification::class,
            'auditable_id' => $verification->id,
        ]);

        // 4. Aprobación final del expediente reenviado
        $this->app['auth']->forgetGuards();
        $this->actingAs($superAdmin)
            ->post(route('admin.teachers.verification.approve', $teacher))
            ->assertRedirect();

        $teacher->refresh();
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $teacher->teacher_verification_status);
        Storage::disk('local')->assertMissing($secondPath);

        // Tras aprobar el reenvío, ve overlay en /dashboard, /groups redirige a dashboard y luego accede 200 OK (ADR-060)
        $this->app['auth']->forgetGuards();
        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false)
            ->assertSee('Confirmar prueba gratuita');
        $this->actingAs($teacher)
            ->get('/groups')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($teacher->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacher->fresh())->get('/groups')->assertOk();
    }

    public function test_mass_assignment_protection_prevents_forging_approved_status_during_registration(): void
    {
        Storage::fake('local');
        Mail::fake();

        $this->assertNotContains('teacher_verification_status', (new User)->getFillable());

        $response = $this->post(route('auth.register.teacher.store'), $this->openRegistrationPayload([
            'email' => 'intento.mass.assignment@example.test',
            'teacher_verification_status' => TeacherVerification::STATUS_APPROVED,
            'status' => TeacherVerification::STATUS_APPROVED,
            'role' => 'super_admin',
        ]));

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'intento.mass.assignment@example.test')->firstOrFail();
        $verification = TeacherVerification::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('teacher', $user->role);
        $this->assertSame(TeacherVerification::STATUS_PENDING, $user->teacher_verification_status);
        $this->assertSame(TeacherVerification::STATUS_PENDING, $verification->status);

        $this->actingAs($user)
            ->get('/groups')
            ->assertRedirect(route('teacher.verification.status'));
    }

    /**
     * Construye un payload válido de registro docente vía abierta con overrides opcionales.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function openRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'privacy_notice_id' => (string) $this->privacyNotice->id,
            'first_name' => 'María Fernanda',
            'last_name' => 'Castillo López',
            'email' => 'docente.abierto@example.test',
            'phone' => '5598765432',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
            'institution_name' => 'Instituto Politécnico Nacional',
            'educational_level' => 'superior',
            'credential_type' => 'institutional_id',
            'verification_document' => UploadedFile::fake()->create('credencial.pdf', 200, 'application/pdf'),
        ], $overrides);
    }

    /**
     * Registra un docente por vía abierta mediante el endpoint HTTP real.
     */
    private function registerPendingTeacherViaHttp(string $email, ?UploadedFile $document = null): User
    {
        $payload = $this->openRegistrationPayload([
            'email' => $email,
            'verification_document' => $document ?? UploadedFile::fake()->create('credencial.pdf', 200, 'application/pdf'),
        ]);

        $this->post(route('auth.register.teacher.store'), $payload)->assertSessionHasNoErrors();

        return User::where('email', $email)->firstOrFail();
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'first_name' => 'Super',
            'last_name' => 'Administrador',
            'email' => uniqid('admin-', true).'@acode-solutions.test',
        ]);
    }

    private function withFreshToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function flushArrayMailTransport(): void
    {
        Mail::mailer('array')->getSymfonyTransport()->flush();
    }

    /**
     * @return list<Email>
     */
    private function capturedEmails(): array
    {
        return Mail::mailer('array')
            ->getSymfonyTransport()
            ->messages()
            ->map(static function (SentMessage $sent): Email {
                $original = $sent->getOriginalMessage();
                if (! $original instanceof Email) {
                    throw new \RuntimeException('Se esperaba un mensaje Symfony Email.');
                }

                return $original;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(Email $email): array
    {
        return array_map(
            static fn (Address $address): string => $address->getAddress(),
            $email->getTo(),
        );
    }
}
