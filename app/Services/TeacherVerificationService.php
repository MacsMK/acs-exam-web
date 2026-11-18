<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TeacherVerificationService.php
 * Ubicación:      app/Services/TeacherVerificationService.php
 * Requerimiento:  RF-AUT-001, RW-03, RW-05, ADR-057 (Verificación Híbrida de
 *                 Identidad Docente y Purga Automática LFPDPPP)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        2.0.0
 * Fecha Creación: 18/11/2026
 * Descripción:    Servicio transaccional que centraliza la acreditación
 *                 híbrida de docentes: canje atómico de códigos institucionales,
 *                 recepción privada de comprobantes, reenvío tras rechazo,
 *                 streaming privado endurecido, dictamen administrativo con
 *                 purga física y de metadatos conforme a LFPDPPP, gestión de
 *                 códigos, consultas administrativas y bitácora de auditoría.
 * ============================================================================
 */

namespace App\Services;

use App\Contracts\ProfessionalLicenseVerifierContract;
use App\Models\AuditLog;
use App\Models\SubscriptionPackage;
use App\Models\TeacherInvitationCode;
use App\Models\TeacherSubscription;
use App\Models\TeacherVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Servicio transaccional de acreditación híbrida de identidad docente.
 *
 * @package App\Services
 */
class TeacherVerificationService
{
    /**
     * Sufijos de dominios académicos y gubernamentales reconocidos como
     * correo institucional informativo (no otorga auto-aprobación por sí solo).
     *
     * @var list<string>
     */
    public const INSTITUTIONAL_DOMAIN_SUFFIXES = [
        '.edu.mx',
        '.edu',
        '.gob.mx',
        '.ipn.mx',
        '.unam.mx',
        '.tecnm.mx',
        '.udg.mx',
        '.buap.mx',
        '.uanl.mx',
        '.uam.mx',
        '.uady.mx',
        '.uv.mx',
        '.uaemex.mx',
        '.uaslp.mx',
        '.ugto.mx',
        '.uson.mx',
        '.uach.mx',
        '.itesm.mx',
        '.tec.mx',
    ];

    /**
     * TLDs institucionales que solo son válidos como sufijo de subdominio
     * (evita falsos positivos sobre dominios desnudos inexistentes).
     *
     * @var list<string>
     */
    private const SUBDOMAIN_ONLY_TLDS = [
        'edu',
        'edu.mx',
        'gob.mx',
    ];

    /**
     * Alfabeto no ambiguo para códigos de invitación de alta entropía.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Cota máxima de intentos para generación de códigos únicos (MPW-V2 §5.2.1).
     */
    private const MAX_CODE_GENERATION_ATTEMPTS = 30;

    /**
     * Disco privado para almacenamiento temporal de comprobantes (LFPDPPP).
     */
    private const STORAGE_DISK = 'local';

    /**
     * Directorio raíz privado donde residen los comprobantes pendientes.
     */
    private const STORAGE_DIRECTORY = 'teacher-verifications';

    /**
     * Mapa estricto MIME real -> extensión segura para comprobantes almacenados.
     *
     * @var array<string, string>
     */
    private const MIME_TO_EXTENSION = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Inicializa el servicio inyectando el verificador enchufable de cédula SEP.
     *
     * @param ProfessionalLicenseVerifierContract $licenseVerifier Driver de validación de cédula profesional.
     */
    public function __construct(
        private readonly ProfessionalLicenseVerifierContract $licenseVerifier,
    ) {
    }

    /**
     * Determina si un correo electrónico pertenece a un dominio institucional
     * educativo o gubernamental reconocido (indicador informativo de confianza).
     *
     * @param string $email Correo electrónico a evaluar.
     * @return bool
     */
    public function isInstitutionalEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));
        $atPosition = strrpos($normalized, '@');

        if ($atPosition === false) {
            return false;
        }

        $domain = substr($normalized, $atPosition + 1);
        if ($domain === '') {
            return false;
        }

        foreach (self::INSTITUTIONAL_DOMAIN_SUFFIXES as $suffix) {
            if (str_ends_with($domain, $suffix)) {
                return true;
            }

            $bareDomain = ltrim($suffix, '.');
            if (! in_array($bareDomain, self::SUBDOMAIN_ONLY_TLDS, true) && $domain === $bareDomain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra la acreditación inicial de una cuenta docente nueva, ya sea por
     * canje inmediato de código institucional o por revisión documental privada.
     *
     * @param User $user Cuenta docente recién creada.
     * @param array<string, mixed> $data Datos validados del formulario de registro.
     * @param UploadedFile|null $document Comprobante documental cuando no se usa código institucional.
     * @param string|null $ipAddress Dirección IP del cliente solicitante.
     * @param string|null $userAgent User-Agent del navegador o cliente móvil.
     * @return TeacherVerification Expediente de verificación creado.
     *
     * @throws ValidationException Si el código es inválido o falta el comprobante requerido.
     * @throws Throwable Si ocurre un error en persistencia o almacenamiento privado.
     */
    public function registerTeacherVerification(
        User $user,
        array $data,
        ?UploadedFile $document,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        $rawCode = isset($data['invitation_code']) && is_string($data['invitation_code'])
            ? trim($data['invitation_code'])
            : '';

        $license = isset($data['professional_license']) && is_string($data['professional_license'])
            ? trim($data['professional_license'])
            : '';

        $licensePayload = $license !== ''
            ? $this->licenseVerifier->verify($license, $user->full_name)
            : null;

        $normalizedLicense = $license !== ''
            ? mb_substr((string) ($licensePayload['details']['license_number'] ?? $license), 0, 12)
            : null;

        $isInstitutionalEmail = $this->isInstitutionalEmail($user->email);

        $educationalLevel = isset($data['educational_level']) && is_string($data['educational_level'])
            && trim($data['educational_level']) !== ''
            ? trim($data['educational_level'])
            : null;

        if ($rawCode !== '') {
            return $this->registerViaInvitationCode(
                $user,
                $rawCode,
                $data,
                $educationalLevel,
                $normalizedLicense,
                $licensePayload,
                $isInstitutionalEmail,
                $ipAddress,
                $userAgent,
            );
        }

        if ($document === null) {
            throw ValidationException::withMessages([
                'verification_document' => ['El campo comprobante de acreditación docente es obligatorio'],
            ]);
        }

        $institutionName = isset($data['institution_name']) && is_string($data['institution_name'])
            ? trim($data['institution_name'])
            : '';

        if ($institutionName === '') {
            throw ValidationException::withMessages([
                'institution_name' => ['El campo institución educativa es obligatorio'],
            ]);
        }

        $credentialType = isset($data['credential_type']) && is_string($data['credential_type'])
            && trim($data['credential_type']) !== ''
            ? trim($data['credential_type'])
            : null;

        $stored = $this->storePrivateDocument($user, $document);

        try {
            return DB::transaction(function () use (
                $user,
                $institutionName,
                $educationalLevel,
                $normalizedLicense,
                $credentialType,
                $stored,
                $isInstitutionalEmail,
                $licensePayload,
                $ipAddress,
                $userAgent,
            ): TeacherVerification {
                $verification = TeacherVerification::create([
                    'user_id' => $user->id,
                    'status' => TeacherVerification::STATUS_PENDING,
                    'verification_method' => TeacherVerification::METHOD_DOCUMENT_REVIEW,
                    'institution_name' => $institutionName,
                    'educational_level' => $educationalLevel,
                    'professional_license' => $normalizedLicense,
                    'credential_type' => $credentialType,
                    'document_path' => $stored['path'],
                    'document_original_name' => $stored['original_name'],
                    'document_mime_type' => $stored['mime_type'],
                    'document_purged_at' => null,
                    'is_institutional_email' => $isInstitutionalEmail,
                    'invitation_code_id' => null,
                    'license_check_payload' => $licensePayload,
                    'submitted_at' => now(),
                ]);

                $user->forceFill([
                    'teacher_verification_status' => TeacherVerification::STATUS_PENDING,
                ])->save();

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'teacher.verification_submitted',
                    'auditable_type' => TeacherVerification::class,
                    'auditable_id' => $verification->id,
                    'new_values' => [
                        'verification_method' => TeacherVerification::METHOD_DOCUMENT_REVIEW,
                        'institution_name' => $verification->institution_name,
                        'credential_type' => $verification->credential_type,
                        'is_institutional_email' => $isInstitutionalEmail,
                        'has_professional_license' => $normalizedLicense !== null,
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => $this->truncateUserAgent($userAgent),
                ]);

                return $verification;
            });
        } catch (Throwable $e) {
            $this->purgeDocumentPath($stored['path']);
            throw $e;
        }
    }

    /**
     * Permite a un docente con solicitud rechazada enviar un nuevo comprobante
     * de acreditación, ejecutando verificación SEP fuera de bloqueos SQL y
     * reingresando el expediente al estado pendiente.
     *
     * @param User $teacher Docente autenticado con estado rechazado.
     * @param array<string, mixed> $data Datos validados del nuevo expediente.
     * @param UploadedFile $document Nuevo comprobante documental privado.
     * @param string|null $ipAddress Dirección IP del solicitante.
     * @param string|null $userAgent User-Agent del navegador.
     * @return TeacherVerification Expediente actualizado en estado pendiente.
     *
     * @throws ValidationException Si la cuenta no corresponde a un docente rechazado o el archivo es inválido.
     * @throws Throwable Si falla la escritura del archivo o la transacción SQL.
     */
    public function resubmit(
        User $teacher,
        array $data,
        UploadedFile $document,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        $existing = TeacherVerification::where('user_id', $teacher->id)->first();

        if (
            $teacher->role !== 'teacher'
            || $existing === null
            || $existing->status !== TeacherVerification::STATUS_REJECTED
            || ! $teacher->isRejectedTeacherVerification()
        ) {
            throw ValidationException::withMessages([
                'verification_document' => [
                    'Solo es posible reenviar el comprobante cuando tu solicitud fue rechazada',
                ],
            ]);
        }

        // Ejecutar verificación SEP antes de abrir transacción y locks de BD (SEC-TV-10).
        $license = isset($data['professional_license']) && is_string($data['professional_license'])
            ? trim($data['professional_license'])
            : '';

        $licensePayload = $license !== ''
            ? $this->licenseVerifier->verify($license, $teacher->full_name)
            : null;

        $normalizedLicense = $license !== ''
            ? mb_substr((string) ($licensePayload['details']['license_number'] ?? $license), 0, 12)
            : null;

        $educationalLevel = isset($data['educational_level']) && is_string($data['educational_level'])
            && trim($data['educational_level']) !== ''
            ? trim($data['educational_level'])
            : null;

        $stored = $this->storePrivateDocument($teacher, $document);
        $oldPath = null;

        try {
            $updated = DB::transaction(function () use (
                $teacher,
                $data,
                $educationalLevel,
                $normalizedLicense,
                $licensePayload,
                $stored,
                $ipAddress,
                $userAgent,
                &$oldPath,
            ): TeacherVerification {
                $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
                $verification = TeacherVerification::where('user_id', $lockedTeacher->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $lockedTeacher->role !== 'teacher'
                    || $verification === null
                    || $verification->status !== TeacherVerification::STATUS_REJECTED
                    || ! $lockedTeacher->isRejectedTeacherVerification()
                ) {
                    throw ValidationException::withMessages([
                        'verification_document' => [
                            'Solo es posible reenviar el comprobante cuando tu solicitud fue rechazada',
                        ],
                    ]);
                }

                $oldPath = $verification->document_path;

                $verification->update([
                    'status' => TeacherVerification::STATUS_PENDING,
                    'verification_method' => TeacherVerification::METHOD_DOCUMENT_REVIEW,
                    'institution_name' => trim((string) $data['institution_name']),
                    'educational_level' => $educationalLevel,
                    'professional_license' => $normalizedLicense,
                    'credential_type' => trim((string) $data['credential_type']),
                    'document_path' => $stored['path'],
                    'document_original_name' => $stored['original_name'],
                    'document_mime_type' => $stored['mime_type'],
                    'document_purged_at' => null,
                    'is_institutional_email' => $this->isInstitutionalEmail($lockedTeacher->email),
                    'license_check_payload' => $licensePayload,
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'resubmission_count' => $verification->resubmission_count + 1,
                ]);

                $lockedTeacher->forceFill([
                    'teacher_verification_status' => TeacherVerification::STATUS_PENDING,
                ])->save();

                $this->syncTeacherStatusInMemory($teacher, TeacherVerification::STATUS_PENDING);

                AuditLog::create([
                    'user_id' => $lockedTeacher->id,
                    'action' => 'teacher.verification_resubmitted',
                    'auditable_type' => TeacherVerification::class,
                    'auditable_id' => $verification->id,
                    'new_values' => [
                        'institution_name' => $verification->institution_name,
                        'credential_type' => $verification->credential_type,
                        'resubmission_count' => $verification->resubmission_count,
                    ],
                    'ip_address' => $ipAddress,
                    'user_agent' => $this->truncateUserAgent($userAgent),
                ]);

                return $verification->fresh() ?? $verification;
            });
        } catch (Throwable $e) {
            $this->purgeDocumentPath($stored['path']);
            throw $e;
        }

        if ($oldPath !== null && $oldPath !== '' && $oldPath !== $stored['path']) {
            $this->purgeDocumentPath($oldPath);
        }

        return $updated;
    }

    /**
     * Permite a un docente en estado pendiente o rechazado acreditarse al
     * instante canjeando un código institucional válido, con bloqueo ordenado
     * (User -> TeacherVerification -> TeacherInvitationCode) y purga LFPDPPP.
     *
     * @param User $teacher Docente pendiente o rechazado.
     * @param string $rawCode Código de invitación ingresado por el docente.
     * @param string|null $ipAddress Dirección IP del solicitante.
     * @param string|null $userAgent User-Agent del navegador.
     * @return TeacherVerification Expediente aprobado mediante código institucional.
     *
     * @throws ValidationException Si la cuenta ya está verificada o el código no es utilizable.
     * @throws ModelNotFoundException Si el usuario docente no existe al adquirir bloqueo.
     */
    public function redeemInvitationCodeForTeacher(
        User $teacher,
        string $rawCode,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        $normalized = TeacherInvitationCode::normalizeCode($rawCode);
        $oldPath = null;

        $verification = DB::transaction(function () use (
            $teacher,
            $normalized,
            $ipAddress,
            $userAgent,
            &$oldPath,
        ): TeacherVerification {
            // Orden determinista de bloqueos anti-TOCTOU (SEC-TV-01): User -> Verification -> Code.
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $existing = TeacherVerification::where('user_id', $lockedTeacher->id)
                ->lockForUpdate()
                ->first();

            if (
                $lockedTeacher->role !== 'teacher'
                || $lockedTeacher->isVerifiedTeacher()
                || $existing?->status === TeacherVerification::STATUS_APPROVED
            ) {
                throw ValidationException::withMessages([
                    'invitation_code' => [
                        'Tu cuenta docente ya se encuentra verificada o no requiere acreditación',
                    ],
                ]);
            }

            $code = TeacherInvitationCode::where('code', $normalized)
                ->lockForUpdate()
                ->first();

            if ($code === null || ! $code->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation_code' => [
                        'El código de invitación no es válido, expiró o alcanzó su límite de usos',
                    ],
                ]);
            }

            $code->increment('uses_count');

            if ($existing !== null) {
                $oldPath = $existing->document_path;

                $existing->update([
                    'status' => TeacherVerification::STATUS_APPROVED,
                    'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                    'institution_name' => trim((string) $existing->institution_name) !== ''
                        ? $existing->institution_name
                        : $code->institution_name,
                    'document_path' => null,
                    'document_original_name' => null,
                    'document_mime_type' => null,
                    'document_purged_at' => $oldPath ? now() : $existing->document_purged_at,
                    'invitation_code_id' => $code->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ]);

                $verification = $existing;
            } else {
                $verification = TeacherVerification::create([
                    'user_id' => $lockedTeacher->id,
                    'status' => TeacherVerification::STATUS_APPROVED,
                    'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                    'institution_name' => $code->institution_name,
                    'is_institutional_email' => $this->isInstitutionalEmail($lockedTeacher->email),
                    'invitation_code_id' => $code->id,
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                ]);
            }

            $lockedTeacher->forceFill([
                'teacher_verification_status' => TeacherVerification::STATUS_APPROVED,
            ])->save();

            $this->syncTeacherStatusInMemory($teacher, TeacherVerification::STATUS_APPROVED);

            AuditLog::create([
                'user_id' => $lockedTeacher->id,
                'action' => 'teacher.verified_via_invitation',
                'auditable_type' => TeacherVerification::class,
                'auditable_id' => $verification->id,
                'new_values' => [
                    'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                    'invitation_code_id' => $code->id,
                    'institution_name' => $verification->institution_name,
                    'document_purged' => (bool) $oldPath,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $verification->fresh() ?? $verification;
        });

        $this->purgeDocumentPath($oldPath);

        return $verification;
    }

    /**
     * Aprueba la acreditación docente en estado pendiente, purga físicamente el
     * comprobante y sus metadatos conforme a la LFPDPPP, registra auditoría y
     * notifica por correo al docente.
     *
     * @param User $teacher Cuenta docente sujeta a dictamen.
     * @param User $admin Superadministrador que emite la aprobación.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return TeacherVerification Expediente aprobado con metadatos de purga.
     *
     * @throws HttpException Si el actor no es super_admin (403) o el objetivo no es teacher (400).
     * @throws ValidationException Si la solicitud no se encuentra en estado pendiente.
     * @throws ModelNotFoundException Si la cuenta docente no existe al bloquear.
     */
    public function approve(
        User $teacher,
        User $admin,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        abort_unless($admin->role === 'super_admin', 403);
        abort_unless($teacher->role === 'teacher', 400);

        $oldPath = null;

        $verification = DB::transaction(function () use (
            $teacher,
            $admin,
            $ipAddress,
            $userAgent,
            &$oldPath,
        ): TeacherVerification {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $existing = TeacherVerification::where('user_id', $lockedTeacher->id)
                ->lockForUpdate()
                ->first();

            if ($existing === null || $existing->status !== TeacherVerification::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'verification' => ['Solo es posible dictaminar solicitudes en estado pendiente'],
                ]);
            }

            $oldPath = $existing->document_path;
            $previousStatus = $existing->status;

            $existing->update([
                'status' => TeacherVerification::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'document_path' => null,
                'document_original_name' => null,
                'document_mime_type' => null,
                'document_purged_at' => $oldPath ? now() : $existing->document_purged_at,
            ]);

            $lockedTeacher->forceFill([
                'teacher_verification_status' => TeacherVerification::STATUS_APPROVED,
            ])->save();

            $this->syncTeacherStatusInMemory($teacher, TeacherVerification::STATUS_APPROVED);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'teacher.verification_approved',
                'auditable_type' => TeacherVerification::class,
                'auditable_id' => $existing->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => [
                    'status' => TeacherVerification::STATUS_APPROVED,
                    'target_teacher_id' => $lockedTeacher->id,
                    'document_purged' => (bool) $oldPath,
                    'institution_name' => $existing->institution_name,
                    'credential_type' => $existing->credential_type,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $existing->fresh() ?? $existing;
        });

        $this->purgeDocumentPath($oldPath);

        $this->sendDecisionEmail(
            $teacher,
            'Cuenta docente aprobada - A-Code Solutions',
            "Hola {$teacher->full_name},\n\n"
            ."Tu acreditación de identidad docente en la Plataforma Evaluadora de A-Code Solutions fue aprobada.\n"
            ."Ya puedes iniciar sesión y utilizar todas las herramientas docentes (grupos, banco de preguntas y exámenes).\n\n"
            ."Conforme a la LFPDPPP, tu comprobante documental fue eliminado de forma segura de nuestros servidores.\n\n"
            ."Equipo A-Code Solutions",
        );

        return $verification;
    }

    /**
     * Rechaza la acreditación docente en estado pendiente indicando un motivo
     * claro, purga el comprobante y sus metadatos conforme a la LFPDPPP,
     * registra auditoría y notifica por correo para permitir un nuevo envío.
     *
     * @param User $teacher Cuenta docente sujeta a dictamen.
     * @param User $admin Superadministrador que emite el rechazo.
     * @param string $reason Motivo normativo del rechazo mostrado al docente.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return TeacherVerification Expediente rechazado con motivo registrado.
     *
     * @throws HttpException Si el actor no es super_admin (403) o el objetivo no es teacher (400).
     * @throws ValidationException Si la solicitud no se encuentra en estado pendiente.
     * @throws ModelNotFoundException Si la cuenta docente no existe al bloquear.
     */
    public function reject(
        User $teacher,
        User $admin,
        string $reason,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        abort_unless($admin->role === 'super_admin', 403);
        abort_unless($teacher->role === 'teacher', 400);

        $cleanReason = trim($reason);
        $oldPath = null;

        $verification = DB::transaction(function () use (
            $teacher,
            $admin,
            $cleanReason,
            $ipAddress,
            $userAgent,
            &$oldPath,
        ): TeacherVerification {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $existing = TeacherVerification::where('user_id', $lockedTeacher->id)
                ->lockForUpdate()
                ->first();

            if ($existing === null || $existing->status !== TeacherVerification::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'verification' => ['Solo es posible dictaminar solicitudes en estado pendiente'],
                ]);
            }

            $oldPath = $existing->document_path;
            $previousStatus = $existing->status;

            $existing->update([
                'status' => TeacherVerification::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $cleanReason,
                'document_path' => null,
                'document_original_name' => null,
                'document_mime_type' => null,
                'document_purged_at' => $oldPath ? now() : $existing->document_purged_at,
            ]);

            $lockedTeacher->forceFill([
                'teacher_verification_status' => TeacherVerification::STATUS_REJECTED,
            ])->save();

            $this->syncTeacherStatusInMemory($teacher, TeacherVerification::STATUS_REJECTED);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'teacher.verification_rejected',
                'auditable_type' => TeacherVerification::class,
                'auditable_id' => $existing->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => [
                    'status' => TeacherVerification::STATUS_REJECTED,
                    'target_teacher_id' => $lockedTeacher->id,
                    'rejection_reason' => $cleanReason,
                    'document_purged' => (bool) $oldPath,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $existing->fresh() ?? $existing;
        });

        $this->purgeDocumentPath($oldPath);

        $this->sendDecisionEmail(
            $teacher,
            'Actualización sobre tu acreditación docente - A-Code Solutions',
            "Hola {$teacher->full_name},\n\n"
            ."Tu solicitud de acreditación docente no pudo ser aprobada por el siguiente motivo:\n"
            ."{$cleanReason}\n\n"
            ."Por seguridad y cumplimiento de la LFPDPPP, el comprobante anterior fue eliminado de nuestros servidores.\n"
            ."Puedes iniciar sesión en la plataforma web para reenviar un nuevo comprobante o canjear un código de invitación institucional.\n\n"
            ."Equipo A-Code Solutions",
        );

        return $verification;
    }

    /**
     * Genera un nuevo código institucional de alta entropía con prefijo
     * configurable y registra su creación en la bitácora de auditoría.
     *
     * @param User $admin Superadministrador emisor del código.
     * @param array<string, mixed> $data Parámetros validados del código institucional.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return TeacherInvitationCode Código institucional persistido.
     *
     * @throws HttpException Si el actor no cuenta con rol super_admin (403).
     * @throws RuntimeException Si no se logra generar un código único dentro de la cota máxima.
     */
    public function createInvitationCode(
        User $admin,
        array $data,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherInvitationCode {
        abort_unless($admin->role === 'super_admin', 403);

        $rawPrefix = isset($data['custom_prefix']) && is_string($data['custom_prefix'])
            ? trim($data['custom_prefix'])
            : '';

        $sanitizedPrefix = $rawPrefix !== ''
            ? strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $rawPrefix))
            : 'ACS';

        $prefix = $sanitizedPrefix !== '' ? $sanitizedPrefix : 'ACS';

        $description = isset($data['description']) && is_string($data['description'])
            && trim($data['description']) !== ''
            ? trim($data['description'])
            : null;

        $expiresAt = ! empty($data['expires_at'])
            ? Carbon::parse((string) $data['expires_at'])
            : null;

        return DB::transaction(function () use (
            $admin,
            $data,
            $prefix,
            $description,
            $expiresAt,
            $ipAddress,
            $userAgent,
        ): TeacherInvitationCode {
            $code = $this->generateUniqueInvitationCode($prefix);

            $invitation = TeacherInvitationCode::create([
                'code' => $code,
                'institution_name' => trim((string) $data['institution_name']),
                'description' => $description,
                'max_uses' => (int) $data['max_uses'],
                'uses_count' => 0,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'invitation_code.created',
                'auditable_type' => TeacherInvitationCode::class,
                'auditable_id' => $invitation->id,
                'new_values' => [
                    'code' => $invitation->code,
                    'institution_name' => $invitation->institution_name,
                    'max_uses' => $invitation->max_uses,
                    'expires_at' => $invitation->expires_at?->toIso8601String(),
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $invitation;
        });
    }

    /**
     * Revoca o reactiva un código de invitación institucional dejando rastro
     * inmutable en la bitácora de auditoría.
     *
     * @param TeacherInvitationCode $invitationCode Código institucional objetivo.
     * @param User $admin Superadministrador que ejecuta el cambio.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return TeacherInvitationCode Instancia actualizada.
     *
     * @throws HttpException Si el actor no cuenta con rol super_admin (403).
     * @throws ModelNotFoundException Si el código ya no existe al bloquear.
     */
    public function toggleInvitationCode(
        TeacherInvitationCode $invitationCode,
        User $admin,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherInvitationCode {
        abort_unless($admin->role === 'super_admin', 403);

        return DB::transaction(function () use (
            $invitationCode,
            $admin,
            $ipAddress,
            $userAgent,
        ): TeacherInvitationCode {
            $locked = TeacherInvitationCode::whereKey($invitationCode->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldState = (bool) $locked->is_active;
            $locked->is_active = ! $oldState;
            $locked->save();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => $locked->is_active ? 'invitation_code.reactivated' : 'invitation_code.revoked',
                'auditable_type' => TeacherInvitationCode::class,
                'auditable_id' => $locked->id,
                'old_values' => ['is_active' => $oldState],
                'new_values' => [
                    'is_active' => $locked->is_active,
                    'code' => $locked->code,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $locked;
        });
    }

    /**
     * Envía por correo electrónico un código de invitación institucional vigente
     * junto con su enlace directo de registro prellenado y registra la acción
     * en la bitácora de auditoría.
     *
     * @param TeacherInvitationCode $invitationCode Código institucional a compartir.
     * @param User $admin Superadministrador que remite la invitación.
     * @param string $recipientEmail Correo destinatario del docente o coordinador.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return void
     *
     * @throws HttpException Si el actor no cuenta con rol super_admin (403).
     * @throws ValidationException Si el código no está activo, expiró o agotó su cupo.
     */
    public function sendInvitationCodeEmail(
        TeacherInvitationCode $invitationCode,
        User $admin,
        string $recipientEmail,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        abort_unless($admin->role === 'super_admin', 403);

        if (! $invitationCode->isUsable()) {
            throw ValidationException::withMessages([
                'invitation_code' => ['El código de invitación no es válido, expiró o alcanzó su límite de usos'],
            ]);
        }

        $normalizedEmail = mb_strtolower(trim($recipientEmail));
        $registrationUrl = route('auth.register.teacher.show', ['codigo' => $invitationCode->code]);
        $expirationText = $invitationCode->expires_at !== null
            ? 'Vigencia hasta: '.$invitationCode->expires_at->format('d/m/Y H:i')."\n"
            : '';

        Mail::raw(
            "Hola,\n\n"
            ."Recibiste una invitación institucional para crear tu cuenta docente en la Plataforma Evaluadora de Exámenes de A-Code Solutions.\n\n"
            ."Institución: {$invitationCode->institution_name}\n"
            ."Código de invitación: {$invitationCode->code}\n"
            .$expirationText
            ."\nPuedes registrarte con acreditación inmediata abriendo directamente este enlace:\n"
            ."{$registrationUrl}\n\n"
            ."Si prefieres ingresarlo manualmente en /register/docente, selecciona \"¿Tienes un código de invitación institucional?\" e introduce el código indicado.\n",
            static function ($message) use ($normalizedEmail, $invitationCode): void {
                $message->to($normalizedEmail)
                    ->subject("Invitación docente ({$invitationCode->institution_name}) - A-Code Solutions");
            },
        );

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'invitation_code.sent',
            'auditable_type' => TeacherInvitationCode::class,
            'auditable_id' => $invitationCode->id,
            'new_values' => [
                'code' => $invitationCode->code,
                'recipient_email' => $normalizedEmail,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $this->truncateUserAgent($userAgent),
        ]);
    }

    /**
     * Valida permisos, prefijo privado de usuario y MIME real en disco antes de
     * entregar metadatos de streaming del comprobante al Superadministrador,
     * registrando la consulta en la bitácora de auditoría.
     *
     * @param User $teacher Cuenta docente titular del comprobante.
     * @param User $admin Superadministrador solicitante.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return array{path: string, mime: string, filename: string}
     *
     * @throws HttpException Si el actor no es super_admin (403), el archivo no existe (404) o el MIME real no es permitido (415).
     */
    public function resolveAdminDocumentStream(
        User $teacher,
        User $admin,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        abort_unless($admin->role === 'super_admin', 403);
        abort_unless($teacher->role === 'teacher', 404);

        $teacher->load('teacherVerification');
        $verification = $teacher->teacherVerification;

        if ($verification === null || ! $verification->hasDocument()) {
            abort(404, 'El comprobante no está disponible o ya fue purgado.');
        }

        $path = (string) $verification->document_path;
        $expectedPrefix = self::STORAGE_DIRECTORY.'/'.$teacher->id.'/';

        if (
            ! str_starts_with($path, $expectedPrefix)
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
        ) {
            abort(404);
        }

        $disk = Storage::disk(self::STORAGE_DISK);
        if (! $disk->exists($path)) {
            abort(404, 'El comprobante no se encontró en el almacenamiento privado.');
        }

        // Validación estricta del MIME real detectado en disco (sin fallback al valor en BD).
        $detectedMime = $disk->mimeType($path);
        if (! is_string($detectedMime) || ! isset(self::MIME_TO_EXTENSION[$detectedMime])) {
            abort(415, 'El comprobante almacenado no corresponde a un formato seguro permitido.');
        }

        $extension = self::MIME_TO_EXTENSION[$detectedMime];
        $filename = sprintf('comprobante-docente-%d.%s', $teacher->id, $extension);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'teacher.verification_document_viewed',
            'auditable_type' => TeacherVerification::class,
            'auditable_id' => $verification->id,
            'new_values' => [
                'target_teacher_id' => $teacher->id,
                'mime_type' => $detectedMime,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $this->truncateUserAgent($userAgent),
        ]);

        return [
            'path' => $path,
            'mime' => $detectedMime,
            'filename' => $filename,
        ];
    }

    /**
     * Obtiene el listado paginado de códigos institucionales con sus creadores
     * precargados y métricas agregadas para el tablero administrativo.
     *
     * @param int $perPage Cantidad de registros por página.
     * @return array{invitations: LengthAwarePaginator<TeacherInvitationCode>, stats: array{total: int, active: int, uses_total: int}}
     */
    public function getInvitationCodesDashboard(int $perPage = 15): array
    {
        $invitations = TeacherInvitationCode::with('creator')
            ->latest()
            ->paginate($perPage);

        $statsRow = TeacherInvitationCode::query()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active, COALESCE(SUM(uses_count), 0) as uses_total',
            )
            ->first();

        return [
            'invitations' => $invitations,
            'stats' => [
                'total' => (int) ($statsRow?->total ?? 0),
                'active' => (int) ($statsRow?->active ?? 0),
                'uses_total' => (int) ($statsRow?->uses_total ?? 0),
            ],
        ];
    }

    /**
     * Construye los datos paginados, filtros y contadores agregados del panel
     * administrativo de gestión y verificación docente evitando N+1.
     *
     * @param array<string, mixed> $filters Filtros opcionales ('search', 'status', 'verification').
     * @param int $perPage Cantidad de docentes por página.
     * @return array{
     *     teachers: LengthAwarePaginator<User>,
     *     packages: \Illuminate\Database\Eloquent\Collection<int, SubscriptionPackage>,
     *     pendingVerificationsCount: int,
     *     verificationCounts: array{all: int, pending: int, approved: int, rejected: int},
     *     filters: array<string, mixed>
     * }
     */
    public function getAdminTeachersIndexData(array $filters, int $perPage = 15): array
    {
        $query = User::where('role', 'teacher')
            ->withCount(['examsCreated', 'teacherSubscriptions'])
            ->with([
                'teacherVerification.invitationCode',
                'teacherVerification.reviewer',
                'latestPaymentTransaction.package',
                'teacherSubscriptions' => static function ($q): void {
                    $q->latest('id')->with('package');
                },
            ])
            ->latest();

        $search = isset($filters['search']) && is_string($filters['search'])
            ? trim($filters['search'])
            : '';

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('teacherVerification', static function ($verificationQuery) use ($search): void {
                        $verificationQuery->where('institution_name', 'ilike', "%{$search}%")
                            ->orWhere('professional_license', 'ilike', "%{$search}%");
                    });
            });
        }

        $statusFilter = isset($filters['status']) && is_string($filters['status'])
            ? trim($filters['status'])
            : '';

        if ($statusFilter !== '') {
            $query->where('is_active', $statusFilter === 'active');
        }

        $verificationFilter = $filters['verification'] ?? null;
        if (in_array($verificationFilter, [
            TeacherVerification::STATUS_PENDING,
            TeacherVerification::STATUS_APPROVED,
            TeacherVerification::STATUS_REJECTED,
        ], true)) {
            $query->where('teacher_verification_status', $verificationFilter);
        }

        $teachers = $query->paginate($perPage)->withQueryString();
        $packages = SubscriptionPackage::where('is_active', true)->orderBy('monthly_price')->get();

        $statusCounts = User::where('role', 'teacher')
            ->selectRaw('teacher_verification_status, COUNT(*) as total')
            ->groupBy('teacher_verification_status')
            ->pluck('total', 'teacher_verification_status');

        $pendingCount = (int) ($statusCounts[TeacherVerification::STATUS_PENDING] ?? 0);
        $approvedCount = (int) ($statusCounts[TeacherVerification::STATUS_APPROVED] ?? 0);
        $rejectedCount = (int) ($statusCounts[TeacherVerification::STATUS_REJECTED] ?? 0);

        return [
            'teachers' => $teachers,
            'packages' => $packages,
            'pendingVerificationsCount' => $pendingCount,
            'verificationCounts' => [
                'all' => $pendingCount + $approvedCount + $rejectedCount,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
            ],
            'filters' => Arr::only($filters, ['search', 'status', 'verification']),
        ];
    }

    /**
     * Alterna transaccionalmente el estado activo (suspensión/reactivación) de
     * una cuenta docente y registra auditoría (RW-05).
     *
     * @param User $teacher Cuenta docente objetivo.
     * @param User $admin Superadministrador que ejecuta el cambio.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return User Cuenta docente actualizada.
     *
     * @throws HttpException Si el actor no es super_admin (403) o el objetivo no es teacher (400).
     * @throws ModelNotFoundException Si la cuenta docente no existe al bloquear.
     */
    public function toggleTeacherActiveStatus(
        User $teacher,
        User $admin,
        ?string $ipAddress,
        ?string $userAgent,
    ): User {
        abort_unless($admin->role === 'super_admin', 403);
        abort_unless($teacher->role === 'teacher', 400);

        return DB::transaction(function () use ($teacher, $admin, $ipAddress, $userAgent): User {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();

            $oldStatus = (bool) $lockedTeacher->is_active;
            $lockedTeacher->is_active = ! $oldStatus;
            $lockedTeacher->save();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => $lockedTeacher->is_active ? 'teacher.reactivated' : 'teacher.suspended',
                'auditable_type' => User::class,
                'auditable_id' => $lockedTeacher->id,
                'old_values' => ['is_active' => $oldStatus],
                'new_values' => ['is_active' => $lockedTeacher->is_active],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $lockedTeacher;
        });
    }

    /**
     * Asigna o actualiza transaccionalmente el paquete de suscripción SaaS de
     * un docente y registra el movimiento en bitácora (RW-05).
     *
     * @param User $teacher Cuenta docente beneficiaria.
     * @param User $admin Superadministrador que asigna el plan.
     * @param array<string, mixed> $validated Datos validados por AssignTeacherPackageRequest.
     * @param string|null $ipAddress Dirección IP del administrador.
     * @param string|null $userAgent User-Agent del navegador administrativo.
     * @return TeacherSubscription Suscripción creada o actualizada.
     *
     * @throws HttpException Si el actor no es super_admin (403) o el objetivo no es teacher (400).
     * @throws ModelNotFoundException Si la cuenta docente no existe al bloquear.
     */
    public function assignSubscriptionPackage(
        User $teacher,
        User $admin,
        array $validated,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherSubscription {
        abort_unless($admin->role === 'super_admin', 403);
        abort_unless($teacher->role === 'teacher', 400);

        return DB::transaction(function () use (
            $teacher,
            $admin,
            $validated,
            $ipAddress,
            $userAgent,
        ): TeacherSubscription {
            $lockedTeacher = User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $subscription = $lockedTeacher->teacherSubscriptions()->latest('id')->lockForUpdate()->first();

            $notes = isset($validated['notes']) && is_string($validated['notes']) && trim($validated['notes']) !== ''
                ? trim($validated['notes'])
                : 'Asignación administrativa por superadministrador';

            $attributes = [
                'subscription_package_id' => (int) $validated['subscription_package_id'],
                'payment_status' => (string) $validated['payment_status'],
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'suspended_at' => $validated['payment_status'] === 'suspended' ? now() : null,
                'notes' => $notes,
            ];

            if ($subscription !== null) {
                $subscription->update($attributes);
            } else {
                $subscription = TeacherSubscription::create([
                    'teacher_id' => $lockedTeacher->id,
                    ...$attributes,
                ]);
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'teacher.subscription_assigned',
                'auditable_type' => TeacherSubscription::class,
                'auditable_id' => $subscription->id,
                'new_values' => [
                    'target_teacher_id' => $lockedTeacher->id,
                    'package_id' => $validated['subscription_package_id'],
                    'payment_status' => $validated['payment_status'],
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $subscription;
        });
    }

    /**
     * Elimina de forma segura un archivo privado de acreditación dentro del
     * prefijo permitido, verificando el borrado físico efectivo en disco y
     * registrando un error crítico ante cualquier falla (LFPDPPP).
     *
     * @param string|null $path Ruta relativa en el disco privado local.
     * @return void
     */
    public function purgeDocumentPath(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        if (
            ! str_starts_with($path, self::STORAGE_DIRECTORY.'/')
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
        ) {
            return;
        }

        try {
            $disk = Storage::disk(self::STORAGE_DISK);
            $deleted = $disk->delete($path);

            if (! $deleted || $disk->exists($path)) {
                Log::error('No fue posible confirmar la purga física LFPDPPP del comprobante docente.', [
                    'path' => $path,
                    'deleted' => $deleted,
                    'still_exists' => $disk->exists($path),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Error de E/S al purgar el comprobante de verificación docente.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ejecuta el flujo de acreditación inmediata por código institucional con
     * bloqueo pesimista para prevenir condiciones de carrera en max_uses.
     *
     * @param User $user Cuenta docente recién creada.
     * @param string $rawCode Código ingresado en el registro.
     * @param array<string, mixed> $data Datos validados del formulario.
     * @param string|null $educationalLevel Nivel educativo declarado.
     * @param string|null $normalizedLicense Cédula normalizada opcional.
     * @param array<string, mixed>|null $licensePayload Respuesta estructurada del verificador SEP.
     * @param bool $isInstitutionalEmail Indicador de dominio institucional.
     * @param string|null $ipAddress Dirección IP del cliente.
     * @param string|null $userAgent User-Agent del cliente.
     * @return TeacherVerification Expediente aprobado.
     *
     * @throws ValidationException Si el código es inválido, expiró o agotó cupo.
     */
    private function registerViaInvitationCode(
        User $user,
        string $rawCode,
        array $data,
        ?string $educationalLevel,
        ?string $normalizedLicense,
        ?array $licensePayload,
        bool $isInstitutionalEmail,
        ?string $ipAddress,
        ?string $userAgent,
    ): TeacherVerification {
        $normalized = TeacherInvitationCode::normalizeCode($rawCode);

        return DB::transaction(function () use (
            $user,
            $normalized,
            $data,
            $educationalLevel,
            $normalizedLicense,
            $licensePayload,
            $isInstitutionalEmail,
            $ipAddress,
            $userAgent,
        ): TeacherVerification {
            $code = TeacherInvitationCode::where('code', $normalized)
                ->lockForUpdate()
                ->first();

            if ($code === null || ! $code->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation_code' => [
                        'El código de invitación no es válido, expiró o alcanzó su límite de usos',
                    ],
                ]);
            }

            $code->increment('uses_count');

            $institutionName = isset($data['institution_name']) && is_string($data['institution_name'])
                && trim($data['institution_name']) !== ''
                ? trim($data['institution_name'])
                : $code->institution_name;

            $verification = TeacherVerification::create([
                'user_id' => $user->id,
                'status' => TeacherVerification::STATUS_APPROVED,
                'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                'institution_name' => $institutionName,
                'educational_level' => $educationalLevel,
                'professional_license' => $normalizedLicense,
                'credential_type' => null,
                'document_path' => null,
                'document_original_name' => null,
                'document_mime_type' => null,
                'document_purged_at' => null,
                'is_institutional_email' => $isInstitutionalEmail,
                'invitation_code_id' => $code->id,
                'license_check_payload' => $licensePayload,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);

            $user->forceFill([
                'teacher_verification_status' => TeacherVerification::STATUS_APPROVED,
            ])->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'teacher.verified_via_invitation',
                'auditable_type' => TeacherVerification::class,
                'auditable_id' => $verification->id,
                'new_values' => [
                    'verification_method' => TeacherVerification::METHOD_INVITATION_CODE,
                    'invitation_code_id' => $code->id,
                    'institution_name' => $verification->institution_name,
                    'is_institutional_email' => $isInstitutionalEmail,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $this->truncateUserAgent($userAgent),
            ]);

            return $verification;
        });
    }

    /**
     * Almacena el comprobante documental en disco privado con nombre UUID y
     * extensión estrictamente derivada del MIME real del archivo (sin fallback
     * a encabezados ni extensiones declaradas por el cliente).
     *
     * @param User $user Docente titular del expediente.
     * @param UploadedFile $document Archivo cargado por el usuario.
     * @return array{path: string, original_name: string, mime_type: string}
     *
     * @throws ValidationException Si el MIME real detectado no pertenece a la allowlist.
     * @throws RuntimeException Si el almacenamiento privado no logra escribir el archivo.
     */
    private function storePrivateDocument(User $user, UploadedFile $document): array
    {
        $realMime = $document->getMimeType();
        if (! is_string($realMime) || ! isset(self::MIME_TO_EXTENSION[$realMime])) {
            throw ValidationException::withMessages([
                'verification_document' => ['El comprobante de acreditación docente no tiene un formato válido'],
            ]);
        }

        $extension = self::MIME_TO_EXTENSION[$realMime];
        $directory = self::STORAGE_DIRECTORY.'/'.$user->id;
        $filename = Str::uuid()->toString().'.'.$extension;

        $storedPath = Storage::disk(self::STORAGE_DISK)->putFileAs($directory, $document, $filename);

        if ($storedPath === false || $storedPath === '') {
            throw new RuntimeException('No fue posible almacenar el comprobante documental en el disco privado.');
        }

        return [
            'path' => $storedPath,
            'original_name' => $this->sanitizeOriginalName($document->getClientOriginalName()),
            'mime_type' => $realMime,
        ];
    }

    /**
     * Sincroniza en memoria el estatus del modelo User recibido por el llamador
     * sin marcar el atributo como sucio.
     *
     * @param User $teacher Instancia en memoria del docente.
     * @param string $status Nuevo estado persistido.
     * @return void
     */
    private function syncTeacherStatusInMemory(User $teacher, string $status): void
    {
        $teacher->forceFill(['teacher_verification_status' => $status]);
        $teacher->syncOriginalAttribute('teacher_verification_status');
    }

    /**
     * Sanitiza el nombre original del archivo removiendo rutas y caracteres de control.
     *
     * @param string $originalName Nombre original reportado por el navegador.
     * @return string Nombre seguro limitado a 200 caracteres.
     */
    private function sanitizeOriginalName(string $originalName): string
    {
        $base = basename(str_replace('\\', '/', $originalName));
        $cleaned = preg_replace('/[\x00-\x1F\x7F]+/u', '', $base) ?? $base;
        $trimmed = trim($cleaned);

        if ($trimmed === '') {
            return 'comprobante';
        }

        return mb_substr($trimmed, 0, 200);
    }

    /**
     * Genera un código único con formato PREFIX-XXXX-XXXX dentro de una cota
     * máxima acotada de intentos (MPW-V2 §5.2.1).
     *
     * @param string $prefix Prefijo alfanumérico sanitizado.
     * @return string Código institucional único.
     *
     * @throws RuntimeException Si se excede MAX_CODE_GENERATION_ATTEMPTS.
     */
    private function generateUniqueInvitationCode(string $prefix): string
    {
        for ($attempt = 1; $attempt <= self::MAX_CODE_GENERATION_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                $prefix,
                $this->randomCodeSegment(4),
                $this->randomCodeSegment(4),
            );

            $exists = TeacherInvitationCode::where('code', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        throw new RuntimeException('No fue posible generar un código institucional único tras múltiples intentos.');
    }

    /**
     * Genera un segmento criptográficamente seguro sobre el alfabeto no ambiguo.
     *
     * @param int $length Longitud de caracteres del segmento.
     * @return string Segmento aleatorio en mayúsculas.
     */
    private function randomCodeSegment(int $length): string
    {
        $alphabetLength = strlen(self::CODE_ALPHABET);
        $segment = '';

        for ($index = 0; $index < $length; $index++) {
            $segment .= self::CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $segment;
    }

    /**
     * Envía la notificación de dictamen por correo sin interrumpir el flujo si
     * el servidor SMTP experimenta fallas transitorias.
     *
     * @param User $teacher Docente destinatario.
     * @param string $subject Asunto del correo electrónico.
     * @param string $body Cuerpo en texto plano.
     * @return void
     */
    private function sendDecisionEmail(User $teacher, string $subject, string $body): void
    {
        try {
            Mail::raw($body, static function ($message) use ($teacher, $subject): void {
                $message->to($teacher->email)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::warning('No fue posible enviar el correo de dictamen de verificación docente.', [
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Acota la longitud de User-Agent al límite de la columna acs_audit_logs.
     *
     * @param string|null $userAgent Cadena User-Agent cruda.
     * @return string|null Cadena truncada a 255 caracteres o null.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}
