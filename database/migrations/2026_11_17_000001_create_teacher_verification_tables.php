<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_17_000001_create_teacher_verification_tables.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-AUT / Verificación Híbrida de Identidad Docente (LFPDPPP)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 17/11/2026
 * Descripción:    Agrega la columna teacher_verification_status en acs_users y
 *                 crea las tablas acs_teacher_invitation_codes y
 *                 acs_teacher_verifications para soportar la acreditación
 *                 híbrida de identidad docente (código institucional, revisión
 *                 documental con purga automática LFPDPPP y validación SEP).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración creando la infraestructura de verificación docente.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('acs_users', function (Blueprint $table): void {
            $table->string('teacher_verification_status', 20)
                ->default('approved')
                ->after('is_active')
                ->index();
        });

        Schema::create('acs_teacher_invitation_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('institution_name', 150);
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('acs_teacher_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('verification_method', 30)->default('document_review');
            $table->string('institution_name', 150);
            $table->string('educational_level', 40)->nullable();
            $table->string('professional_license', 12)->nullable();
            $table->string('credential_type', 40)->nullable();
            $table->string('document_path', 255)->nullable();
            $table->string('document_original_name', 255)->nullable();
            $table->string('document_mime_type', 60)->nullable();
            $table->timestamp('document_purged_at')->nullable();
            $table->boolean('is_institutional_email')->default(false);
            $table->foreignId('invitation_code_id')
                ->nullable()
                ->constrained('acs_teacher_invitation_codes')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->jsonb('license_check_payload')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedSmallInteger('resubmission_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Revierte la migración eliminando tablas, índices y columnas en orden inverso.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_teacher_verifications');
        Schema::dropIfExists('acs_teacher_invitation_codes');

        Schema::table('acs_users', function (Blueprint $table): void {
            $table->dropIndex(['teacher_verification_status']);
            $table->dropColumn('teacher_verification_status');
        });
    }
};
