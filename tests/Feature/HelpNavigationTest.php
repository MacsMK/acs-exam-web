<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TeacherVerification;
use App\Models\User;
use Tests\TestCase;

class HelpNavigationTest extends TestCase
{
    public function test_help_and_support_are_accessible_before_signing_in(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSeeText('Abrir inicio de sesión')
            ->assertSee('href="'.route('help.support').'"', false);

        $this->get(route('help.support'))
            ->assertOk()
            ->assertSeeText('No recibí el código de verificación')
            ->assertSeeText('Folio de incidente')
            ->assertSee('href="'.route('help.index').'"', false);
    }

    public function test_teacher_help_exposes_working_footer_destinations_and_a_skip_link(): void
    {
        $teacher = User::factory()->make(['role' => 'teacher']);
        $teacher->setRelation('teacherVerification', null);
        $teacher->setRelation('subscription', null);

        $this->actingAs($teacher)->get(route('help.index'))
            ->assertOk()
            ->assertSee('href="'.route('teacher.ai-credits.index').'"', false)
            ->assertSee('href="'.route('teacher.reviews.index').'"', false)
            ->assertSee('href="'.route('help.index').'"', false)
            ->assertSee('href="'.route('help.support').'"', false)
            ->assertSee('href="#contenido-principal"', false)
            ->assertSee('id="contenido-principal" tabindex="-1"', false);

        $this->get(route('help.support'))->assertOk();
    }

    public function test_teacher_help_respects_verification_and_plan_onboarding(): void
    {
        $teacher = User::factory()->make(['role' => 'teacher']);
        $teacher->teacher_verification_status = TeacherVerification::STATUS_PENDING;

        $this->actingAs($teacher)->get(route('help.index'))
            ->assertOk()
            ->assertSeeText('Abrir verificación de cuenta')
            ->assertDontSeeText('Abrir Créditos de IA');

        $teacher->teacher_verification_status = TeacherVerification::STATUS_APPROVED;
        $teacher->setRelation('teacherVerification', new TeacherVerification);
        $teacher->setRelation('subscription', null);

        $this->get(route('help.index'))
            ->assertOk()
            ->assertSeeText('Abrir selección de plan')
            ->assertDontSeeText('Abrir Créditos de IA');
    }

    public function test_student_and_administrator_help_show_shortcuts_for_their_roles(): void
    {
        $student = User::factory()->make(['role' => 'student']);
        $this->actingAs($student)->get(route('help.index'))
            ->assertOk()
            ->assertSeeText('Abrir mis exámenes')
            ->assertDontSeeText('Abrir Créditos de IA');

        $administrator = User::factory()->make(['role' => 'super_admin']);
        $this->actingAs($administrator)->get(route('help.index'))
            ->assertOk()
            ->assertSeeText('Abrir docentes')
            ->assertDontSeeText('Abrir mis exámenes');
    }
}
