<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Group;
use App\Models\User;
use App\Services\GradebookEntryService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GradebookHubUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_group_on_another_page_and_matches_only_the_teachers_data(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $target = $this->group($teacher, 'Ética 100% nivel_1', 'HUBEXACT', 'Filosofía', true);
        $period = $this->period($target, 'Primer periodo de evaluación de Filosofía');
        $this->group($otherTeacher, 'Ética 100% nivel_1 privada', 'HUBOTHER', 'Asignatura ajena', true);

        for ($index = 0; $index < 12; $index++) {
            $this->group($teacher, 'Ética 100A nivelX1 '.$index, 'HUBPAG'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'Historia');
        }

        $this->actingAs($teacher)->get(route('teacher.gradebook.hub'))
            ->assertOk()
            ->assertDontSeeText($target->name)
            ->assertDontSeeText('Asignatura ajena');

        $this->get(route('teacher.gradebook.hub', ['search' => 'hubexact']), [
            'X-Catalog-Fragment' => 'teacher-gradebook-results',
        ])->assertOk()
            ->assertSee('data-search-status="1 grupo encontrado"', false)
            ->assertSeeText($period->name)
            ->assertDontSeeText('Buscar grupos');

        $this->get(route('teacher.gradebook.hub', ['search' => 'Filosofia', 'status' => 'closed', 'subject' => 'Filosofía']))
            ->assertOk()
            ->assertSeeText('Grupo cerrado')
            ->assertSeeText($target->name)
            ->assertDontSeeText('Ética 100A nivelX1');

        $this->get(route('teacher.gradebook.hub', ['search' => 'ETICA 100% NIVEL_1']))
            ->assertOk()
            ->assertViewHas('groups', static fn ($groups): bool => $groups->getCollection()->modelKeys() === [$target->id]);

        $this->get(route('teacher.gradebook.hub', ['search' => 'HUBOTHER']))
            ->assertOk()
            ->assertSeeText('No hay grupos que coincidan')
            ->assertDontSeeText('Crear grupo');
    }

    public function test_summary_counts_assignments_for_all_filtered_groups_and_pagination_retains_filters(): void
    {
        $teacher = User::factory()->teacher()->create();
        $oldest = $this->group($teacher, 'Álgebra grupo anterior', 'HUBOLD01', 'Álgebra');

        for ($index = 0; $index < 12; $index++) {
            $group = $this->group($teacher, "Álgebra grupo {$index}", 'HUBALG'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'Álgebra');
        }

        $excluded = $this->group($teacher, 'Grupo fuera de los resultados', 'HUBFIL01', 'Filosofía');
        $this->period($oldest, 'Periodo anterior');
        $this->period($group, 'Periodo reciente');
        $this->period($excluded, 'Periodo fuera del filtro');
        $exam = Exam::create([
            'teacher_id' => $teacher->id,
            'title' => 'Examen asignado a varios grupos',
            'share_token' => 'HUB-EXAM01',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'grading_scale' => '0-100',
            'is_published' => false,
        ]);
        $exam->groups()->attach([$oldest->id, $group->id, $excluded->id]);

        $filters = ['search' => 'algebra', 'status' => 'active', 'subject' => 'Álgebra'];
        $firstPage = $this->actingAs($teacher)->get(route('teacher.gradebook.hub', $filters))
            ->assertOk()
            ->assertViewHas('stats.periods', 2)
            ->assertViewHas('stats.examAssignments', 2)
            ->assertSeeText('13 grupos encontrados')
            ->assertSeeText('Exámenes asignados')
            ->assertDontSeeText('Exámenes aplicados')
            ->assertDontSeeText($oldest->name);
        $pagination = $this->xpath($firstPage)->query("//a[@rel='next']")->item(0);
        self::assertNotNull($pagination);
        parse_str(parse_url($pagination->getAttribute('href'), PHP_URL_QUERY), $pageQuery);
        self::assertEquals($filters + ['page' => '2'], $pageQuery);

        $this->get(route('teacher.gradebook.hub', $filters + ['page' => 2]))
            ->assertOk()
            ->assertViewHas('stats.periods', 2)
            ->assertViewHas('stats.examAssignments', 2)
            ->assertSeeText($oldest->name)
            ->assertDontSeeText($excluded->name);

        $this->get(route('teacher.gradebook.hub', $filters + ['page' => 9]))
            ->assertRedirect(route('teacher.gradebook.hub', $filters + ['page' => 2]));
    }

    public function test_empty_states_and_period_links_keep_clear_primary_actions_and_context(): void
    {
        $teacher = User::factory()->teacher()->create();
        $hub = route('teacher.gradebook.hub');
        $initial = $this->actingAs($teacher)->get($hub)
            ->assertOk()
            ->assertSeeText('Sin grupos escolares registrados')
            ->assertDontSeeText('Buscar grupos');
        self::assertSame(1, $this->xpath($initial)->query("//a[contains(., 'Crear grupo')]")->length);

        $group = $this->group($teacher, 'Grupo con periodos', 'HUBGRP01', 'Programación');
        $empty = $this->group($teacher, 'Grupo sin periodos', 'HUBEMP01');
        $closed = $this->group($teacher, 'Grupo cerrado sin periodos', 'HUBCLS01', null, true);
        $name = 'Primer periodo de demostración con un nombre compartido que debe verse completo';
        $first = $this->period($group, $name, now()->subWeeks(2)->toDateString());
        $second = $this->period($group, $name, now()->subWeek()->toDateString());

        $result = $this->get($hub)
            ->assertOk()
            ->assertSeeText('Buscar grupos')
            ->assertSeeText('Calificaciones por grupo')
            ->assertSeeText('No hay periodos creados aún.')
            ->assertSeeText($name)
            ->assertSeeText($first->starts_at->format('d/m/Y'))
            ->assertSeeText($second->starts_at->format('d/m/Y'))
            ->assertDontSeeText('Crear grupo');
        $xpath = $this->xpath($result);
        $primaryPeriodLinks = $xpath->query("//a[contains(@aria-label, 'Abrir calificaciones de ') and contains(@class, 'bg-acode-primary')]");
        self::assertSame(2, $primaryPeriodLinks->length);
        foreach ($primaryPeriodLinks as $link) {
            self::assertStringContainsString($name, $link->getAttribute('aria-label'));
            self::assertStringContainsString($group->name, $link->getAttribute('aria-label'));
        }
        self::assertSame(1, $xpath->query("//a[contains(@aria-label, '{$empty->name}') and contains(@class, 'bg-acode-primary')]")->length);
        self::assertSame(0, $xpath->query("//a[contains(@aria-label, '{$closed->name}') and contains(@class, 'bg-acode-primary')]")->length);
    }

    public function test_gradebook_preserves_hub_filters_and_keeps_the_student_page_independent(): void
    {
        $teacher = User::factory()->teacher()->create();
        $group = $this->group($teacher, 'Grupo de captura', 'HUBBACK1', 'Historia');
        for ($index = 0; $index < 12; $index++) {
            $this->group($teacher, "Grupo {$index}", 'HUBNAV'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'Historia');
        }
        $period = $this->period($group, 'Periodo de captura');
        $students = User::factory()->count(GradebookEntryService::PAGE_SIZE + 1)->create();
        $group->students()->attach($students->modelKeys(), ['joined_at' => now()]);
        $lastStudent = app(GradebookEntryService::class)->students($group)->forPage(2, GradebookEntryService::PAGE_SIZE)->firstOrFail();
        $filters = ['search' => 'grupo', 'status' => 'active', 'subject' => 'Historia', 'page' => 2];
        $hub = route('teacher.gradebook.hub', $filters);

        $result = $this->actingAs($teacher)->get($hub)->assertOk();
        $link = $this->xpath($result)->query("//a[starts-with(@aria-label, 'Abrir calificaciones')]")->item(0);
        self::assertNotNull($link);
        $gradebook = $link->getAttribute('href');
        parse_str(parse_url($gradebook, PHP_URL_QUERY), $context);
        self::assertEquals([
            'from_gradebook' => '1',
            'gradebook_search' => 'grupo',
            'gradebook_status' => 'active',
            'gradebook_subject' => 'Historia',
            'gradebook_hub_page' => '2',
        ], $context);

        $this->get($gradebook)->assertOk()->assertSeeText('Volver a calificaciones')
            ->assertSee(e($hub), false);
        $studentPage = route('groups.periods.gradebook', [$group, $period, ...$context, 'page' => 2]);
        $save = route('groups.periods.gradebook.save', [$group, $period, ...$context]);
        $this->get($studentPage)->assertOk()->assertSeeText($lastStudent->full_name)->assertSee(e($save), false);
        $this->get(route('groups.periods.gradebook', [$group, $period, ...$context, 'page' => 9]))
            ->assertRedirect($studentPage);

        $grades = ['gradebook_page' => 2, 'gradebook_complete' => 1, 'grades' => [
            ['student_id' => $lastStudent->id, 'homework_component' => 90],
        ]];
        $this->from($studentPage)->post($save, $grades)->assertRedirect($studentPage);
        $this->assertDatabaseHas('acs_student_period_grades', [
            'evaluation_period_id' => $period->id,
            'student_id' => $lastStudent->id,
            'homework_component' => 90,
        ]);
        $grades['grades'][0]['homework_component'] = 101;
        $this->from($studentPage)->post($save, $grades)
            ->assertRedirect($studentPage)
            ->assertSessionHasErrors('grades.0.homework_component');
    }

    public function test_first_group_and_first_period_flow_returns_to_the_catalog(): void
    {
        $teacher = User::factory()->teacher()->create();
        $origin = ['from_gradebook' => 1];
        $hub = route('teacher.gradebook.hub');
        $createGroup = route('groups.create', $origin);
        $storeGroup = route('groups.store', $origin);

        $this->actingAs($teacher)->get($hub)->assertOk()->assertSee($createGroup, false);
        $this->get($createGroup)->assertOk()->assertSeeText('Volver a calificaciones')->assertSee($storeGroup, false);
        $this->post($storeGroup, ['name' => ''])->assertRedirect($createGroup)->assertSessionHasErrors('name');
        $created = $this->post($storeGroup, ['name' => 'Mi primer grupo', 'subject' => 'Historia']);
        $group = $teacher->groupsAsTeacher()->where('name', 'Mi primer grupo')->firstOrFail();
        $groupUrl = route('groups.show', [$group, ...$origin]);
        $periodsUrl = route('groups.periods.index', [$group, ...$origin]);
        $storePeriod = route('groups.periods.store', [$group, ...$origin]);

        $created->assertRedirect($groupUrl);
        $this->get($groupUrl)->assertOk()
            ->assertSeeText('Volver a calificaciones')
            ->assertSee($periodsUrl, false);
        $this->get($periodsUrl)->assertOk()
            ->assertSeeText('Volver a calificaciones')
            ->assertSee($storePeriod, false);

        $this->post($storePeriod, ['name' => ''])->assertRedirect($periodsUrl)->assertSessionHasErrors('name');
        $this->post($storePeriod, [
            'name' => 'Primer periodo',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
            'exams_weight' => 0,
            'homework_weight' => 100,
            'project_weight' => 0,
            'other_weight' => 0,
        ])->assertRedirect($periodsUrl)->assertSessionHasNoErrors();

        $period = $group->evaluationPeriods()->firstOrFail();
        $gradebook = route('groups.periods.gradebook', [$group, $period, ...$origin]);
        $this->get($periodsUrl)->assertOk()->assertSee($gradebook, false);
        $this->get($hub)->assertOk()->assertSee($gradebook, false);
        $this->get($gradebook)->assertOk()
            ->assertSeeText('Volver a calificaciones')
            ->assertSeeText('No hay estudiantes inscritos');
        $this->get(route('groups.periods.gradebook', [$group, $period]))->assertOk()
            ->assertSeeText('Volver a periodos');
    }

    private function group(User $teacher, string $name, string $folio, ?string $subject = null, bool $closed = false): Group
    {
        return Group::create([
            'teacher_id' => $teacher->id,
            'name' => $name,
            'access_folio' => $folio,
            'subject' => $subject,
            'is_closed' => $closed,
        ]);
    }

    private function period(Group $group, string $name, ?string $startsAt = null): EvaluationPeriod
    {
        return $group->evaluationPeriods()->create([
            'name' => $name,
            'formula' => 'exams*0+homework*1+project*0+other*0',
            'starts_at' => $startsAt ?? now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
