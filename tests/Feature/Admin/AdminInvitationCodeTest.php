<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        AdminInvitationCodeTest.php
 * Ubicación:      tests/Feature/Admin/AdminInvitationCodeTest.php
 * Requerimiento:  RF-AUT-001, RW-05, RNF-SEG-008, ADR-057 (Códigos de
 *                 Invitación Institucional y Purga Automática LFPDPPP)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Certificación integral del ciclo de vida de los códigos de
 *                 invitación institucional: emisión exclusiva por super_admin,
 *                 restricción 403 a otros roles, registro docente vía rápida
 *                 con aprobación instantánea, rechazo genérico de códigos
 *                 inválidos/expirados/revocados/agotados, revocación y
 *                 reactivación auditadas, y canje posterior con purga física
 *                 automática de comprobantes previos conforme a la LFPDPPP.
 * ============================================================================
 */

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\PrivacyNotice;
use App\Models\TeacherInvitationCode;
use App\Models\TeacherVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas Feature para administración y canje de códigos de invitación docente.
 *
 * @package Tests\Feature\Admin
 */
class AdminInvitationCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PrivacyNotice $privacyNotice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'super_admin',
            'first_name' => 'Admin',
            'last_name' => 'A-Code',
            'email' => 'admin.invitations@acode-solutions.test',
        ]);

        $this->privacyNotice = PrivacyNotice::create([
            'version' => '2.0',
            'content' => 'Aviso de privacidad vigente.',
            'published_at' => now()->subMinute(),
        ]);
    }

    public function test_super_admin_can_create_invitation_code_and_non_admins_receive_403(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $payload = [
            'institution_name' => 'Instituto Politécnico Nacional',
            'description' => 'Convenio institucional 2026-2',
            'max_uses' => 25,
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'custom_prefix' => 'ipn',
        ];

        // Invitado -> redirige a login
        $this->get(route('admin.invitations.index'))->assertRedirect('/login');
        $this->post(route('admin.invitations.store'), $payload)->assertRedirect('/login');

        // Docente -> 403
        $this->actingAs($teacher)->get(route('admin.invitations.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.invitations.store'), $payload)->assertForbidden();

        // Alumno -> 403
        $this->actingAs($student)->get(route('admin.invitations.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.invitations.store'), $payload)->assertForbidden();

        $this->assertDatabaseCount('acs_teacher_invitation_codes', 0);

        // Super Admin -> 200 en listado y alta exitosa con auditoría
        $this->actingAs($this->admin)->get(route('admin.invitations.index'))->assertOk();

        $createResponse = $this->actingAs($this->admin)
            ->post(route('admin.invitations.store'), $payload);

        $createResponse->assertRedirect(route('admin.invitations.index'));
        $createResponse->assertSessionHasNoErrors();
        $createResponse->assertSessionHas('status');

        $invitation = TeacherInvitationCode::firstOrFail();
        $this->assertMatchesRegularExpression('/^IPN-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $invitation->code);
        $this->assertSame('Instituto Politécnico Nacional', $invitation->institution_name);
        $this->assertSame('Convenio institucional 2026-2', $invitation->description);
        $this->assertSame(25, $invitation->max_uses);
        $this->assertSame(0, $invitation->uses_count);
        $this->assertTrue($invitation->is_active);
        $this->assertSame($this->admin->id, $invitation->created_by);
        $this->assertNotNull($invitation->expires_at);

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'invitation_code.created',
            'auditable_type' => TeacherInvitationCode::class,
            'auditable_id' => $invitation->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.invitations.index'))
            ->assertOk()
            ->assertSee($invitation->code)
            ->assertSee('Instituto Politécnico Nacional')
            ->assertSee(route('auth.register.teacher.show', ['codigo' => $invitation->code]), false);
    }

    public function test_teacher_register_form_prefills_invitation_code_from_query_and_admin_can_send_invitation_email(): void
    {
        config(['mail.default' => 'array']);
        $this->app->make('mailer')->getSymfonyTransport()->flush();

        $invitation = TeacherInvitationCode::create([
            'code' => 'IPN-4455-6677',
            'institution_name' => 'Instituto Politécnico Nacional',
            'max_uses' => 10,
            'uses_count' => 0,
            'expires_at' => now()->addDays(10),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // 1. El enlace directo ?codigo= prellena el formulario y muestra detección de invitación
        $this->get(route('auth.register.teacher.show', ['codigo' => ' ipn-4455-6677 ']))
            ->assertOk()
            ->assertSee('Invitación institucional detectada')
            ->assertSee('IPN-4455-6677');

        // 2. Un docente no puede enviar invitaciones
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)
            ->post(route('admin.invitations.send-email', $invitation), ['email' => 'colega@ipn.mx'])
            ->assertForbidden();

        // 3. El Superadmin envía el enlace directo por correo y se audita
        $response = $this->actingAs($this->admin)
            ->post(route('admin.invitations.send-email', $invitation), [
                'email' => 'NuevoDocente@ipn.mx',
            ]);

        $response->assertRedirect(route('admin.invitations.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'invitation_code.sent',
            'auditable_type' => TeacherInvitationCode::class,
            'auditable_id' => $invitation->id,
        ]);

        $messages = $this->app->make('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $rawBody = (string) $messages->first()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('IPN-4455-6677', $rawBody);
        $this->assertStringContainsString('codigo=IPN-4455-6677', $rawBody);
    }

    public function test_fast_track_teacher_registration_with_valid_invitation_code_approves_instantly_and_grants_dashboard_after_2fa(): void
    {
        Storage::fake('local');
        Mail::fake();

        $invitation = TeacherInvitationCode::create([
            'code' => 'TECNM-7K9P-3M4Q',
            'institution_name' => 'Tecnológico Nacional de México',
            'description' => 'Semestre otoño',
            'max_uses' => 5,
            'uses_count' => 0,
            'expires_at' => now()->addDays(15),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // Registro vía rápida: solo invitation_code (con espacios y minúsculas para validar normalización),
        // sin comprobante, sin tipo de comprobante ni nombre de institución.
        $registerResponse = $this->post(route('auth.register.teacher.store'), [
            'privacy_notice_id' => (string) $this->privacyNotice->id,
            'first_name' => 'Sofía',
            'last_name' => 'Hernández Ruiz',
            'email' => 'sofia.hernandez@tecnm.mx',
            'phone' => '5511223344',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
            'invitation_code' => '  tecnm-7k9p-3m4q  ',
        ]);

        $user = User::where('email', 'sofia.hernandez@tecnm.mx')->firstOrFail();

        $registerResponse->assertRedirect(route('auth.verify-2fa.show', ['user' => $user->id]));
        $registerResponse->assertSessionHasNoErrors();

        $this->assertSame('teacher', $user->role);
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $user->teacher_verification_status);
        $this->assertTrue($user->isVerifiedTeacher());

        $verification = TeacherVerification::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $verification->status);
        $this->assertSame(TeacherVerification::METHOD_INVITATION_CODE, $verification->verification_method);
        $this->assertSame('Tecnológico Nacional de México', $verification->institution_name);
        $this->assertSame($invitation->id, $verification->invitation_code_id);
        $this->assertNull($verification->document_path);
        $this->assertTrue($verification->is_institutional_email);
        $this->assertNotNull($verification->reviewed_at);

        $this->assertSame(1, $invitation->fresh()->uses_count);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->assertDatabaseHas('acs_audit_logs', [
            'user_id' => $user->id,
            'action' => 'teacher.verified_via_invitation',
            'auditable_type' => TeacherVerification::class,
            'auditable_id' => $verification->id,
        ]);

        // Tras completar 2FA, /dashboard muestra el overlay bloqueante (ADR-060) y /groups redirige a dashboard
        $twoFactorResponse = $this->withSession(['pending_2fa_user_id' => $user->id])
            ->post(route('auth.verify-2fa.store'), [
                'user_id' => $user->id,
                'code' => (string) $user->fresh()->two_factor_code,
            ]);

        $twoFactorResponse->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false)
            ->assertSee('Confirmar prueba gratuita');
        $this->get(route('groups.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');

        $this->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('dashboard'));

        $this->app['auth']->forgetGuards();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->get(route('groups.index'))->assertOk();
    }

    public function test_registration_rejects_nonexistent_expired_revoked_and_exhausted_codes_with_generic_message(): void
    {
        Storage::fake('local');
        Mail::fake();

        $expiredCode = TeacherInvitationCode::create([
            'code' => 'EXP-1111-2222',
            'institution_name' => 'Universidad Expirada',
            'max_uses' => 10,
            'uses_count' => 0,
            'expires_at' => now()->subMinute(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $revokedCode = TeacherInvitationCode::create([
            'code' => 'REV-3333-4444',
            'institution_name' => 'Universidad Revocada',
            'max_uses' => 10,
            'uses_count' => 0,
            'expires_at' => now()->addDays(10),
            'is_active' => false,
            'created_by' => $this->admin->id,
        ]);

        $exhaustedCode = TeacherInvitationCode::create([
            'code' => 'AGO-5555-6666',
            'institution_name' => 'Universidad Agotada',
            'max_uses' => 2,
            'uses_count' => 2,
            'expires_at' => now()->addDays(10),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $genericMessage = 'El código de invitación no es válido, expiró o alcanzó su límite de usos';

        $cases = [
            'inexistente' => 'NOEXISTE-9999-8888',
            'expirado' => $expiredCode->code,
            'revocado' => $revokedCode->code,
            'agotado' => $exhaustedCode->code,
        ];

        foreach ($cases as $label => $codeValue) {
            $email = "rechazo.{$label}@example.test";

            $response = $this->post(route('auth.register.teacher.store'), [
                'privacy_notice_id' => (string) $this->privacyNotice->id,
                'first_name' => 'Roberto',
                'last_name' => 'Díaz Peña',
                'email' => $email,
                'phone' => '5599887766',
                'password' => 'Secret1234',
                'password_confirmation' => 'Secret1234',
                'accepts_privacy_notice' => '1',
                'invitation_code' => $codeValue,
            ]);

            $response->assertSessionHasErrors([
                'invitation_code' => $genericMessage,
            ]);

            $this->assertDatabaseMissing('acs_users', ['email' => $email]);
        }

        $this->assertDatabaseCount('acs_teacher_verifications', 0);
        $this->assertSame(0, $expiredCode->fresh()->uses_count);
        $this->assertSame(0, $revokedCode->fresh()->uses_count);
        $this->assertSame(2, $exhaustedCode->fresh()->uses_count);

        // Anti-oráculo (SEC-TV-05): un payload incompleto no consulta ni revela validez de invitation_code
        $incompleteProbe = $this->postJson('/api/v1/auth/register/teacher', [
            'invitation_code' => $expiredCode->code,
        ]);
        $incompleteProbe->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'password'])
            ->assertJsonMissingValidationErrors(['invitation_code']);
    }

    public function test_super_admin_can_revoke_and_reactivate_invitation_code_with_audit_logs(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $invitation = TeacherInvitationCode::create([
            'code' => 'UAM-8888-7777',
            'institution_name' => 'Universidad Autónoma Metropolitana',
            'max_uses' => 10,
            'uses_count' => 0,
            'expires_at' => now()->addDays(30),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // Restricción 403 para roles no administrativos
        $this->actingAs($teacher)->post(route('admin.invitations.toggle', $invitation))->assertForbidden();
        $this->actingAs($student)->post(route('admin.invitations.toggle', $invitation))->assertForbidden();
        $this->assertTrue($invitation->fresh()->is_active);

        // 1. Revocar código
        $revokeResponse = $this->actingAs($this->admin)
            ->post(route('admin.invitations.toggle', $invitation));

        $revokeResponse->assertRedirect(route('admin.invitations.index'));
        $revokeResponse->assertSessionHas('status', "El código de invitación {$invitation->code} fue revocado correctamente.");
        $this->assertFalse($invitation->fresh()->is_active);

        $revokeAudit = AuditLog::where('action', 'invitation_code.revoked')
            ->where('auditable_type', TeacherInvitationCode::class)
            ->where('auditable_id', $invitation->id)
            ->first();

        $this->assertNotNull($revokeAudit);
        $this->assertSame($this->admin->id, $revokeAudit->user_id);
        $this->assertTrue((bool) ($revokeAudit->old_values['is_active'] ?? false));
        $this->assertFalse((bool) ($revokeAudit->new_values['is_active'] ?? true));

        // 2. Reactivar código
        $reactivateResponse = $this->actingAs($this->admin)
            ->post(route('admin.invitations.toggle', $invitation));

        $reactivateResponse->assertRedirect(route('admin.invitations.index'));
        $reactivateResponse->assertSessionHas('status', "El código de invitación {$invitation->code} fue reactivado correctamente.");
        $this->assertTrue($invitation->fresh()->is_active);

        $reactivateAudit = AuditLog::where('action', 'invitation_code.reactivated')
            ->where('auditable_type', TeacherInvitationCode::class)
            ->where('auditable_id', $invitation->id)
            ->first();

        $this->assertNotNull($reactivateAudit);
        $this->assertSame($this->admin->id, $reactivateAudit->user_id);
        $this->assertFalse((bool) ($reactivateAudit->old_values['is_active'] ?? true));
        $this->assertTrue((bool) ($reactivateAudit->new_values['is_active'] ?? false));
    }

    public function test_pending_teacher_redeeming_invitation_code_approves_immediately_increments_uses_and_purges_document(): void
    {
        Storage::fake('local');
        Mail::fake();

        // 1. El docente se registra por vía abierta subiendo un comprobante privado
        $this->post(route('auth.register.teacher.store'), [
            'privacy_notice_id' => (string) $this->privacyNotice->id,
            'first_name' => 'Andrés',
            'last_name' => 'Salazar Cruz',
            'email' => 'andres.salazar@gmail.com',
            'phone' => '5544332211',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
            'institution_name' => 'Preparatoria Federal 1',
            'educational_level' => 'media_superior',
            'credential_type' => 'institutional_id',
            'verification_document' => UploadedFile::fake()->create('credencial_previa.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $teacher = User::where('email', 'andres.salazar@gmail.com')->firstOrFail();
        $verification = $teacher->teacherVerification()->firstOrFail();
        $originalPath = (string) $verification->document_path;

        $this->assertSame(TeacherVerification::STATUS_PENDING, $teacher->teacher_verification_status);
        Storage::disk('local')->assertExists($originalPath);

        $invitation = TeacherInvitationCode::create([
            'code' => 'PREP-2468-ACEG',
            'institution_name' => 'Dirección General de Bachillerato',
            'max_uses' => 3,
            'uses_count' => 0,
            'expires_at' => now()->addDays(7),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        // 2. Canje con código inexistente falla sin alterar el comprobante ni aprobar la cuenta
        $this->actingAs($teacher)
            ->post(route('teacher.verification.redeem-code'), [
                'invitation_code' => 'INVALIDO-0000-0000',
            ])
            ->assertSessionHasErrors([
                'invitation_code' => 'El código de invitación no es válido, expiró o alcanzó su límite de usos',
            ]);

        $this->assertSame(TeacherVerification::STATUS_PENDING, $teacher->fresh()->teacher_verification_status);
        Storage::disk('local')->assertExists($originalPath);

        // 3. Canje del código válido desde /docente/verificacion/canjear-codigo
        $redeemResponse = $this->actingAs($teacher)
            ->post(route('teacher.verification.redeem-code'), [
                'invitation_code' => '  prep-2468-aceg ',
            ]);

        $redeemResponse->assertRedirect(route('dashboard'));
        $redeemResponse->assertSessionHasNoErrors();
        $redeemResponse->assertSessionHas('status');

        $teacher->refresh();
        $verification->refresh();
        $invitation->refresh();

        $this->assertSame(TeacherVerification::STATUS_APPROVED, $teacher->teacher_verification_status);
        $this->assertSame(TeacherVerification::STATUS_APPROVED, $verification->status);
        $this->assertSame(TeacherVerification::METHOD_INVITATION_CODE, $verification->verification_method);
        $this->assertSame($invitation->id, $verification->invitation_code_id);
        $this->assertNull($verification->document_path);
        $this->assertNull($verification->document_original_name);
        $this->assertNull($verification->document_mime_type);
        $this->assertNotNull($verification->document_purged_at);
        $this->assertNotNull($verification->reviewed_at);
        $this->assertSame(1, $invitation->uses_count);

        // Purga física automática del comprobante previo (LFPDPPP)
        Storage::disk('local')->assertMissing($originalPath);

        $redeemAudit = AuditLog::where('action', 'teacher.verified_via_invitation')
            ->where('auditable_type', TeacherVerification::class)
            ->where('auditable_id', $verification->id)
            ->first();

        $this->assertNotNull($redeemAudit);
        $this->assertTrue((bool) ($redeemAudit->new_values['document_purged'] ?? false));

        // Primer acceso muestra overlay bloqueante en /dashboard y redirige /groups a /dashboard (ADR-060)
        $this->actingAs($teacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('inert', false)
            ->assertSee('onboarding_overlay', false)
            ->assertSee('Confirmar prueba gratuita');
        $this->actingAs($teacher)
            ->get(route('groups.index'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($teacher)
            ->post(route('teacher.subscription.free-trial'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($teacher->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('onboarding_overlay', false);
        $this->actingAs($teacher->fresh())->get(route('groups.index'))->assertOk();
        $this->actingAs($teacher->fresh())
            ->post(route('teacher.verification.redeem-code'), ['invitation_code' => $invitation->code])
            ->assertForbidden();
    }
}
