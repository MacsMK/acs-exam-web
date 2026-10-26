<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     2026_10_26_191000_bd01_2026_023_create_acs_user_devices_table.php
 * Ubicación:   database/migrations/
 * Versión:     1.0.0
 * Descripción: Token FCM del dispositivo móvil de un usuario (push, si se
 *              usa). Diseño de Balam (Módulo 1).
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('fcm_token', 512);
            $table->enum('platform', ['android', 'ios']);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_user_devices');
    }
};
