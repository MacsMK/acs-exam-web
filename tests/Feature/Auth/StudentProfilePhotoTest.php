<?php

declare(strict_types=1);

/**
 * ============================================================================
 * A-CODE SOLUTIONS - ANALIZAMOS, DISEÑAMOS, TRANSFORMAMOS
 * ============================================================================
 * Archivo:     StudentProfilePhotoTest.php
 * Ubicación:   tests/Feature/Auth/StudentProfilePhotoTest.php
 * Requerimiento: RF-AUT-008, RF-AUT-009 (ACT-WEB-038)
 * Descripción: Fotografía opcional del estudiante (JPG o PNG hasta 5 MB) en
 *              el registro web y por API, guardada en disco privado y visible
 *              solo para quien tiene permiso; sin fotografía se muestra la
 *              imagen predeterminada.
 * ============================================================================
 */

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\PrivacyNotice;
use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProfilePhotoService::DISK);
    }

    public function test_web_registration_stores_the_optional_photo_privately(): void
    {
        $this->post(route('auth.register.student.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('yo.png', 300, 300)->size(800),
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'alumna@example.test')->firstOrFail();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk(ProfilePhotoService::DISK)->assertExists($user->profile_photo_path);
    }

    public function test_photo_must_be_jpg_or_png_up_to_5_mb(): void
    {
        $this->post(route('auth.register.student.store'), $this->payload([
            'photo' => UploadedFile::fake()->create('foto.gif', 100, 'image/gif'),
        ]))->assertSessionHasErrors(['photo' => 'La fotografía debe ser una imagen JPG o PNG']);

        $this->post(route('auth.register.student.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('grande.jpg')->size(6000),
        ]))->assertSessionHasErrors(['photo' => 'La fotografía no debe exceder los 5 MB']);

        $this->assertFalse(User::where('email', 'alumna@example.test')->exists());
    }

    public function test_api_registration_accepts_the_photo(): void
    {
        $this->post('/api/v1/auth/register/student', $this->payload([
            'photo' => UploadedFile::fake()->image('yo.jpg')->size(500),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertNotNull(User::where('email', 'alumna@example.test')->value('profile_photo_path'));
    }

    public function test_api_shows_the_official_message_when_the_photo_does_not_reach_the_server(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'foto');
        file_put_contents($path, 'parcial');
        $photo = new UploadedFile($path, 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->post('/api/v1/auth/register/student', $this->payload(['photo' => $photo]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', 'La fotografía no debe exceder los 5 MB');

        $this->assertFalse(User::where('email', 'alumna@example.test')->exists());
    }

    public function test_without_photo_the_default_image_is_shown(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Imagen predeterminada de', false);
    }

    public function test_photo_is_visible_to_owner_and_group_teacher_but_not_to_others(): void
    {
        $student = $this->student();
        app(ProfilePhotoService::class)->store($student, UploadedFile::fake()->image('yo.jpg'));

        $teacher = $this->user('teacher', 'docente@example.test');
        $group = Group::create(['teacher_id' => $teacher->id, 'name' => 'A', 'access_folio' => 'FOTO1234', 'is_closed' => false]);
        $group->students()->attach($student->id, ['joined_at' => now()]);
        $stranger = $this->user('student', 'otro@example.test');

        $this->actingAs($student)->get(route('users.photo', $student))->assertOk();
        $this->actingAs($teacher)->get(route('users.photo', $student))->assertOk();
        $this->actingAs($stranger)->get(route('users.photo', $student))->assertForbidden();
    }

    public function test_student_replaces_photo_from_profile_and_old_file_is_removed(): void
    {
        $student = $this->student();
        $photos = app(ProfilePhotoService::class);
        $photos->store($student, UploadedFile::fake()->image('vieja.jpg'));
        $oldPath = $student->fresh()->profile_photo_path;

        $this->actingAs($student)
            ->post(route('account.profile.photo'), ['photo' => UploadedFile::fake()->image('nueva.png')->size(300)])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status');

        $newPath = $student->fresh()->profile_photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk(ProfilePhotoService::DISK)->assertExists($newPath);
        Storage::disk(ProfilePhotoService::DISK)->assertMissing($oldPath);
    }

    public function test_photo_update_validates_format_and_is_only_for_students(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->post(route('account.profile.photo'), ['photo' => UploadedFile::fake()->create('foto.gif', 50, 'image/gif')])
            ->assertSessionHasErrors(['photo' => 'La fotografía debe ser una imagen JPG o PNG']);

        $admin = $this->user('super_admin', 'admin@example.test');
        $this->actingAs($admin)
            ->post(route('account.profile.photo'), ['photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
    }

    private function payload(array $extra = []): array
    {
        $notice = PrivacyNotice::first() ?? PrivacyNotice::create([
            'version' => '1.0',
            'content' => 'Aviso vigente.',
            'published_at' => now()->subMinute(),
        ]);

        return array_merge([
            'privacy_notice_id' => (string) $notice->id,
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => 'alumna@example.test',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'accepts_privacy_notice' => '1',
        ], $extra);
    }

    private function student(): User
    {
        return $this->user('student', uniqid('alumno-', true).'@example.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'role' => $role,
            'first_name' => 'Ana',
            'last_name' => 'López',
            'email' => $email,
            'phone' => '5512345678',
            'password' => 'Demo1234',
            'is_active' => true,
            'two_factor_verified_at' => now(),
        ]);
    }
}
