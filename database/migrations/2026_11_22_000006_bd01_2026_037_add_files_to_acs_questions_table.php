<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_22_000006_bd01_2026_037_add_files_to_acs_questions_table.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-BAN-012 (imágenes), RF-BAN-013 (archivos PDF)
 * Folio BD-01:    BD01-2026-037
 * Actividad:      ACT-WEB-026
 * Autor:          Meneses Mendoza Elizabeth (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 22/11/2026
 * Descripción:    Agrega a acs_questions las columnas image_path y pdf_path:
 *                 ruta en el disco privado de la imagen (JPG/PNG) y del PDF
 *                 opcionales de la pregunta, uno de cada tipo. Nulas cuando
 *                 la pregunta no tiene archivo.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->string('image_path', 255)->nullable();
            $table->string('pdf_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'pdf_path']);
        });
    }
};
