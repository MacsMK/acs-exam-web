<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Group;
use App\Models\User;
use App\Services\ExamAssemblyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Examen de demo ya armado, asignado al grupo demo y publicado, para que
 * la expo no tenga que armar uno desde cero antes de poder mostrarlo.
 */
class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'docente.demo@acode-solutions.test')->firstOrFail();
        $group = Group::where('teacher_id', $teacher->id)->firstOrFail();

        $exam = Exam::firstOrCreate(
            ['teacher_id' => $teacher->id, 'title' => 'Examen Demo - Parcial 1'],
            [
                'description' => 'Examen de demostración para la expo.',
                'grading_scale' => '0-10',
                'randomize_question_order' => true,
                'result_visibility_mode' => 'final_score',
                'require_webcam' => false,
                'max_allowed_incidents' => 3,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addMonth(),
                'share_token' => Str::random(32),
                'is_published' => false,
            ],
        );

        if ($exam->sections()->exists()) {
            $this->command?->info("Examen demo ya armado (id {$exam->id}).");

            return;
        }

        $exam->groups()->syncWithoutDetaching([$group->id]);

        $assembly = app(ExamAssemblyService::class);

        $sections = [
            ['title' => 'Opción múltiple', 'question_type' => 'multiple_choice', 'requested_quantity' => 2],
            ['title' => 'Relación de columnas', 'question_type' => 'matching', 'requested_quantity' => 1],
            ['title' => 'Preguntas abiertas', 'question_type' => 'open', 'requested_quantity' => 1],
            ['title' => 'Programación', 'question_type' => 'code', 'requested_quantity' => 1],
        ];

        foreach ($sections as $index => $data) {
            $section = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => $data['title'],
                'question_type' => $data['question_type'],
                'requested_quantity' => $data['requested_quantity'],
                'sort_order' => $index,
            ]);

            $assembly->drawQuestionsForSection($section);
        }

        $exam->update(['is_published' => true]);

        $this->command?->info("Examen demo publicado (id {$exam->id}), asignado al grupo {$group->access_folio}.");
    }
}
