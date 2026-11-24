<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        ComplianceSchemaUpgradeTest.php
 * Ubicación:      tests/Feature/ComplianceSchemaUpgradeTest.php
 * Requerimiento:  RF-ADM-011 y 015, RF-CAL-010, RF-EXA-012
 * Autor:          Codex (implementación local)
 * Versión:        1.0.0
 * Fecha Creación: 23/11/2026
 * Descripción:    Comprueba la compatibilidad de las migraciones con registros anteriores.
 * ============================================================================
 */

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Group;
use App\Models\StudentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ComplianceSchemaUpgradeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Aplica y revierte las tres migraciones sobre datos sintéticos previos.
     *
     * @return void
     */
    public function test_legacy_data_survives_upgrade_and_an_isolated_rollback(): void
    {
        $migrations = [];
        foreach (['039_track_trial_usage_and_terms', '040_add_grading_configuration', '041_add_free_exam_timing'] as $migration) {
            $paths = glob(database_path('migrations/*_bd01_2026_'.$migration.'.php'));
            $this->assertCount(1, $paths);
            $migrations[] = require $paths[0];
        }
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $teacher = User::factory()->create(['role' => 'teacher']);
        $group = Group::create(['teacher_id' => $teacher->id, 'name' => 'Registro anterior', 'access_folio' => 'LEGACY01']);
        $exam = Exam::create(['teacher_id' => $teacher->id, 'title' => 'Examen anterior', 'share_token' => 'legacy',
            'starts_at' => now(), 'ends_at' => now()->addHour()]);
        $period = $group->evaluationPeriods()->create(['name'=>'Anterior','formula'=>'homework*1','starts_at'=>today(),'ends_at'=>today()]);
        $student = User::factory()->create(['role' => 'student']);
        $period->studentGrades()->create(['student_id' => $student->id, 'homework_component' => 87.25, 'final_grade' => 87.25]);
        foreach ($migrations as $migration) {
            $migration->up();
        }

        $teacher->refresh();
        $this->assertFalse($teacher->trial_history_complete);
        $this->assertTrue((bool) $teacher->legacy_exam_record);
        $this->assertTrue((bool) $teacher->legacy_group_record);
        $this->assertNull($teacher->trial_exam_used_at);
        $this->assertNull($group->fresh()->grading_scale);
        $this->assertNull($exam->fresh()->free_duration_minutes);
        $this->assertTrue(User::factory()->create()->fresh()->trial_history_complete);
        $this->assertEquals(87.25, $period->studentGrades()->sole()->final_grade);

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $this->assertFalse(Schema::hasColumn('acs_exams', 'free_duration_minutes'));
        $this->assertFalse(Schema::hasTable('acs_period_exam_weights'));
        $this->assertEquals(87.25, DB::table('acs_student_period_grades')->where('student_id', $student->id)->value('final_grade'));
    }

    /**
     * Comprueba la actualización del esquema con un intento y una cuenta ya registrados.
     * @return void
     */
    public function test_critical_migrations_preserve_existing_accounts_and_attempts(): void
    {
        $migrations = [];
        foreach (['042', '043', '044'] as $folio) {
            $paths = glob(database_path('migrations/*_bd01_2026_'.$folio.'_*.php'));
            $this->assertCount(1, $paths);
            $migrations[] = require $paths[0];
        }
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $group = Group::create(['teacher_id' => $teacher->id, 'name' => 'Grupo previo', 'access_folio' => 'CMP044']);
        $group->students()->attach($student->id, ['joined_at' => now()]);
        $exam = Exam::create(['teacher_id' => $teacher->id, 'title' => 'Examen previo', 'share_token' => 'CMP044',
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $exam->groups()->attach($group);
        $attempt = StudentAttempt::create(['group_id' => $group->id, 'exam_id' => $exam->id, 'student_id' => $student->id,
            'status' => 'submitted', 'started_at' => now()->subMinute(), 'submitted_at' => now(),
            'final_score' => 87.25, 'question_sequence_payload' => []]);

        foreach ($migrations as $migration) {
            $migration->up();
        }
        $this->assertNull($student->fresh()->two_factor_delivery);
        $this->assertSame(0, $student->fresh()->two_factor_failed_attempts);
        $this->assertNull($attempt->fresh()->presenter_hash);
        $this->assertTrue(Schema::hasTable('acs_attempt_drafts'));
        $this->assertSame('87.25', $attempt->fresh()->final_score);

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $this->assertFalse(Schema::hasTable('acs_attempt_drafts'));
        $this->assertFalse(Schema::hasColumn('acs_users', 'two_factor_delivery'));
        $this->assertSame('87.25', $attempt->fresh()->final_score);
        $this->assertDatabaseHas('acs_users', ['id' => $student->id]);
    }
}
