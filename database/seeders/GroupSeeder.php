<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Un grupo de demo con los alumnos sembrados por UserSeeder ya inscritos.
 */
class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'docente.demo@acode-solutions.test')->firstOrFail();

        $group = Group::firstOrCreate(
            ['teacher_id' => $teacher->id, 'name' => 'Auditoría de Sistemas - Grupo Demo'],
            [
                'subject' => 'Auditoría de Sistemas',
                'access_folio' => strtoupper(Str::random(8)),
            ],
        );

        $students = User::where('role', 'student')->get();

        foreach ($students as $student) {
            $group->students()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
        }

        $this->command?->info("Grupo demo folio: {$group->access_folio}");
    }
}
