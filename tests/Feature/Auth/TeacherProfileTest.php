<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_personal_details_do_not_change_the_profile(): void
    {
        $teacher = User::factory()->teacher()->create();
        $url = route('teacher.profile.index');
        $originalDetails = $teacher->only(['id', 'first_name', 'last_name', 'phone']);

        $this->actingAs($teacher)->from($url)
            ->put(route('teacher.profile.update'), [
                'first_name' => 'Elena121',
                'last_name' => 'Ríos212',
                'phone' => 'dasda',
            ])
            ->assertRedirect($url)
            ->assertSessionHasErrors(['first_name', 'last_name', 'phone']);

        $this->assertDatabaseHas('acs_users', $originalDetails);
    }

    public function test_profile_accepts_compound_names_and_a_ten_digit_phone(): void
    {
        $teacher = User::factory()->teacher()->create();
        $url = route('teacher.profile.index');
        $details = [
            'first_name' => 'María José',
            'last_name' => 'Ríos Núñez',
            'phone' => '0512345678',
        ];

        $this->actingAs($teacher)->from($url)
            ->put(route('teacher.profile.update'), $details)
            ->assertRedirect($url)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('acs_users', ['id' => $teacher->id] + $details);
    }
}
