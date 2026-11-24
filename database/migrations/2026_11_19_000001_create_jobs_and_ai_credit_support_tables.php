<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_19_000001_create_jobs_and_ai_credit_support_tables.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-IAR-011 / RF-IAR-014 / MC-V3 (Cola de evaluación IA y
 *                 control de créditos de IA por suscripción)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 19/11/2026
 * Descripción:    Crea las tablas estándar de cola de Laravel (jobs y
 *                 failed_jobs) si no existen, agrega la preferencia docente
 *                 skip_ai_reevaluation_warning en acs_users, añade columnas de
 *                 trazabilidad IA (ai_is_fallback, ai_model_used) en
 *                 acs_student_answers, crea acs_ai_credit_usages y establece
 *                 los cupos base de IA para los paquetes basic y advanced.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración creando el soporte de colas, trazabilidad IA,
     * tabla de consumo de créditos y cupos base de suscripción.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('acs_users', 'skip_ai_reevaluation_warning')) {
            Schema::table('acs_users', function (Blueprint $table): void {
                $table->boolean('skip_ai_reevaluation_warning')->default(false);
            });
        }

        Schema::table('acs_student_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('acs_student_answers', 'ai_is_fallback')) {
                $table->boolean('ai_is_fallback')->default(false);
            }

            if (! Schema::hasColumn('acs_student_answers', 'ai_model_used')) {
                $table->string('ai_model_used', 60)->nullable();
            }
        });

        Schema::create('acs_ai_credit_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('student_answer_id')
                ->nullable()
                ->constrained('acs_student_answers')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedSmallInteger('credits_consumed')->default(1);
            $table->boolean('is_reevaluation')->default(false);
            $table->string('model_used', 60)->nullable();
            $table->string('status', 20)->default('consumed');
            $table->timestamps();

            $table->index(['teacher_id', 'created_at']);
            $table->index('student_answer_id');
        });

        DB::table('acs_subscription_packages')
            ->where('tier', 'basic')
            ->whereNull('ai_requests_limit')
            ->update(['ai_requests_limit' => 200]);

        DB::table('acs_subscription_packages')
            ->where('tier', 'advanced')
            ->whereNull('ai_requests_limit')
            ->update(['ai_requests_limit' => 600]);
    }

    /**
     * Revierte la migración eliminando la tabla de créditos de IA, las
     * columnas agregadas en respuestas y usuarios, y las tablas de colas en
     * orden inverso seguro.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('acs_ai_credit_usages');

        $answerColumnsToDrop = array_values(array_filter(
            ['ai_is_fallback', 'ai_model_used'],
            static fn (string $column): bool => Schema::hasColumn('acs_student_answers', $column)
        ));

        if ($answerColumnsToDrop !== []) {
            Schema::table('acs_student_answers', function (Blueprint $table) use ($answerColumnsToDrop): void {
                $table->dropColumn($answerColumnsToDrop);
            });
        }

        if (Schema::hasColumn('acs_users', 'skip_ai_reevaluation_warning')) {
            Schema::table('acs_users', function (Blueprint $table): void {
                $table->dropColumn('skip_ai_reevaluation_warning');
            });
        }

        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
