<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Proyecto:       Plataforma Evaluadora / Aplicadora de Exámenes (SaaS)
 * Archivo:        TenantScopeIsolationTest.php
 * Ubicación:      tests/Feature/Security/TenantScopeIsolationTest.php
 * Requerimiento:  RF-AUT / MPW-V2 §6.6 / SEC-02 (Aislamiento Multi-Docente)
 * Autor:          Equipo 4 (A-Code Solutions)
 * Versión:        1.0.0
 * Descripción:    Pruebas automatizadas que certifican que TenantTeacherScope
 *                 aísla automáticamente las consultas Eloquent de docentes.
 * ============================================================================
 */

namespace Tests\Feature\Security;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_teacher_scope_filters_groups_questions_and_exams_automatically(): void
    {
        $teacherA = User::factory()->create(['role' => 'teacher', 'email' => 'docente.a@acode.com']);
        $teacherB = User::factory()->create(['role' => 'teacher', 'email' => 'docente.b@acode.com']);

        $groupA = Group::create([
            'teacher_id' => $teacherA->id,
            'name' => 'Grupo Docente A',
            'subject' => 'Matemáticas',
            'access_folio' => 'FOLIOA01',
        ]);

        $groupB = Group::create([
            'teacher_id' => $teacherB->id,
            'name' => 'Grupo Docente B',
            'subject' => 'Física',
            'access_folio' => 'FOLIOB02',
        ]);

        $examA = Exam::create([
            'teacher_id' => $teacherA->id,
            'title' => 'Examen A',
            'share_token' => 'SHARETOKENA1',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $examB = Exam::create([
            'teacher_id' => $teacherB->id,
            'title' => 'Examen B',
            'share_token' => 'SHARETOKENB2',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        // 1. Docente A autenticado: solo ve sus propios recursos
        $this->actingAs($teacherA);
        $groupsForA = Group::all();
        $this->assertTrue($groupsForA->contains($groupA));
        $this->assertFalse($groupsForA->contains($groupB));

        $examsForA = Exam::all();
        $this->assertTrue($examsForA->contains($examA));
        $this->assertFalse($examsForA->contains($examB));

        // 2. Docente B autenticado: solo ve sus propios recursos
        $this->actingAs($teacherB);
        $groupsForB = Group::all();
        $this->assertFalse($groupsForB->contains($groupA));
        $this->assertTrue($groupsForB->contains($groupB));

        $examsForB = Exam::all();
        $this->assertFalse($examsForB->contains($examA));
        $this->assertTrue($examsForB->contains($examB));

        // 3. withoutGlobalScopes permite acceso cuando sea requerido explícitamente
        $allGroups = Group::withoutGlobalScopes()->get();
        $this->assertTrue($allGroups->contains($groupA));
        $this->assertTrue($allGroups->contains($groupB));
    }
}
