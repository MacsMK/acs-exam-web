<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        2026_11_12_000001_create_acs_subjects_table_and_normalize_subject_links.php
 * Ubicación:      database/migrations/
 * Requerimiento:  RF-BAN (Normalización de Materias en Banco y Armado, Módulo 5)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Fecha Creación: 12/11/2026
 * Descripción:    Crea el catálogo normalizado acs_subjects por docente, enlaza
 *                 acs_questions.subject_id y acs_exam_sections.subject_tag/
 *                 subject_id (nullable para retrocompatibilidad API) y ejecuta
 *                 backfill idempotente sobre reactivos existentes.
 * ============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Models\Subject;

return new class extends Migration
{
    /**
     * Ejecuta la migración creando acs_subjects y normalizando enlaces.
     */
    public function up(): void
    {
        Schema::create('acs_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')
                ->constrained('acs_users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('normalized_name', 120);
            $table->timestamps();

            $table->unique(['teacher_id', 'normalized_name'], 'acs_subjects_teacher_normalized_unique');
        });

        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->foreignId('subject_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('acs_subjects')
                ->nullOnDelete();

            $table->index(['teacher_id', 'subject_id']);
        });

        Schema::table('acs_exam_sections', function (Blueprint $table): void {
            $table->string('subject_tag', 100)
                ->nullable()
                ->after('question_type');
            $table->foreignId('subject_id')
                ->nullable()
                ->after('subject_tag')
                ->constrained('acs_subjects')
                ->nullOnDelete();
        });

        $this->backfillQuestionSubjects();
    }

    /**
     * Revierte las columnas, llaves foráneas y la tabla acs_subjects.
     */
    public function down(): void
    {
        Schema::table('acs_exam_sections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn('subject_tag');
        });

        Schema::table('acs_questions', function (Blueprint $table): void {
            $table->dropIndex(['teacher_id', 'subject_id']);
            $table->dropConstrainedForeignId('subject_id');
        });

        Schema::dropIfExists('acs_subjects');
    }

    /**
     * Pobla acs_subjects y enlaza acs_questions existentes (incluyendo soft-deleted)
     * normalizando subject_tag de manera segura para PostgreSQL.
     */
    private function backfillQuestionSubjects(): void
    {
        $now = now();
        /** @var array<string, array{id: int, name: string}> $subjectCache */
        $subjectCache = [];

        DB::table('acs_questions')
            ->select(['id', 'teacher_id', 'subject_tag'])
            ->whereNotNull('subject_tag')
            ->orderBy('id')
            ->chunkById(200, static function ($questions) use (&$subjectCache, $now): void {
                foreach ($questions as $question) {
                    $cleanName = Subject::cleanDisplayName((string) $question->subject_tag);

                    if ($cleanName === null) {
                        DB::table('acs_questions')
                            ->where('id', $question->id)
                            ->update([
                                'subject_id' => null,
                                'subject_tag' => null,
                            ]);
                        continue;
                    }

                    $normalizedName = mb_substr(Subject::normalizeName($cleanName), 0, 120, 'UTF-8');

                    if ($normalizedName === '') {
                        DB::table('acs_questions')
                            ->where('id', $question->id)
                            ->update([
                                'subject_id' => null,
                                'subject_tag' => null,
                            ]);
                        continue;
                    }

                    $displayName = mb_substr($cleanName, 0, 100, 'UTF-8');
                    $cacheKey = $question->teacher_id . ':' . $normalizedName;

                    if (! isset($subjectCache[$cacheKey])) {
                        $existing = DB::table('acs_subjects')
                            ->select(['id', 'name'])
                            ->where('teacher_id', $question->teacher_id)
                            ->where('normalized_name', $normalizedName)
                            ->first();

                        if ($existing !== null) {
                            $subjectCache[$cacheKey] = [
                                'id' => (int) $existing->id,
                                'name' => (string) $existing->name,
                            ];
                        } else {
                            $insertedId = (int) DB::table('acs_subjects')->insertGetId([
                                'teacher_id' => $question->teacher_id,
                                'name' => $displayName,
                                'normalized_name' => $normalizedName,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);

                            $subjectCache[$cacheKey] = [
                                'id' => $insertedId,
                                'name' => $displayName,
                            ];
                        }
                    }

                    DB::table('acs_questions')
                        ->where('id', $question->id)
                        ->update([
                            'subject_id' => $subjectCache[$cacheKey]['id'],
                            'subject_tag' => $subjectCache[$cacheKey]['name'],
                        ]);
                }
            });
    }
};
