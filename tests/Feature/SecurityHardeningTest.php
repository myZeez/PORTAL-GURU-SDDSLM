<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Monev;
use App\Filament\Pages\MyProfile;
use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\TeacherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_cannot_open_another_teachers_monev_detail(): void
    {
        $teacher = User::factory()->create();
        $colleague = User::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(Monev::class)
            ->call('selectTeacher', $colleague->id)
            ->assertForbidden();
    }

    public function test_the_selected_monev_teacher_cannot_be_overwritten_from_the_browser(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Monev::class)->set('selectedTeacherId', User::factory()->create()->id);
    }

    public function test_leadership_can_still_pick_any_teacher_in_monev(): void
    {
        $colleague = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(Monev::class)
            ->call('selectTeacher', $colleague->id)
            ->assertSet('selectedTeacherId', $colleague->id);
    }

    public function test_profile_photo_rejects_svg_files_at_both_upload_layers(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $svg = fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        Livewire::test(MyProfile::class)->fillForm(['photo_path' => $svg()])->call('save');
        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('public')->assertDirectoryEmpty('avatars');

        config(['livewire.temporary_file_upload.rules' => ['required', 'file']]);

        Livewire::test(MyProfile::class)
            ->fillForm(['photo_path' => $svg()])
            ->call('save')
            ->assertHasFormErrors(['photo_path']);
    }

    public function test_profile_photo_rejects_files_over_two_megabytes_at_both_upload_layers(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $oversized = fn (): UploadedFile => UploadedFile::fake()->createWithContent('big.png', $tinyPng.random_bytes(3 * 1024 * 1024));

        Livewire::test(MyProfile::class)->fillForm(['photo_path' => $oversized()])->call('save');
        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('public')->assertDirectoryEmpty('avatars');

        config(['livewire.temporary_file_upload.rules' => ['required', 'file']]);

        Livewire::test(MyProfile::class)
            ->fillForm(['photo_path' => $oversized()])
            ->call('save')
            ->assertHasFormErrors(['photo_path']);
    }

    public function test_profile_photo_accepts_a_normal_png(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->fillForm(['photo_path' => UploadedFile::fake()->image('me.png', 200, 200)->size(100)])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertExists($user->fresh()->photo_path);
    }

    public function test_profile_form_cannot_change_role_or_account_flags(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(MyProfile::class)
            ->set('data.role', Role::Developer->value)
            ->set('data.is_active', false)
            ->set('data.must_change_password', true)
            ->call('save');

        $user->refresh();

        $this->assertNull($user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
    }

    public function test_profile_rejects_malformed_identity_numbers(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MyProfile::class)
            ->fillForm(['nik' => '12345', 'nuptk' => 'abc'])
            ->call('save')
            ->assertHasFormErrors(['nik', 'nuptk']);
    }

    public function test_link_fields_only_accept_http_and_https(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        foreach (['ftp://files.example/a', 'data://text/html,x'] as $link) {
            Livewire::test(ManageTasks::class)
                ->callAction('create', [
                    'title' => 'Tugas',
                    'starts_on' => now()->toDateString(),
                    'due_on' => now()->addDay()->toDateString(),
                    'link' => $link,
                ])
                ->assertHasActionErrors(['link']);
        }
    }

    public function test_a_user_can_change_their_own_password(): void
    {
        $user = User::factory()->create(['password' => 'LamaPass123', 'must_change_password' => true]);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'currentPassword' => 'LamaPass123',
                'password' => 'BaruPass456',
                'passwordConfirmation' => 'BaruPass456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('BaruPass456', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'LamaPass123']);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'currentPassword' => 'SalahPass999',
                'password' => 'BaruPass456',
                'passwordConfirmation' => 'BaruPass456',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('LamaPass123', $user->fresh()->password));
    }

    public function test_new_passwords_must_contain_letters_and_numbers(): void
    {
        $user = User::factory()->create(['password' => 'LamaPass123']);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'currentPassword' => 'LamaPass123',
                'password' => '1234567890',
                'passwordConfirmation' => '1234567890',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_the_forced_password_change_cannot_be_skipped_by_submitting_nothing(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->fillForm(['password' => ''])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_accounts_flagged_for_a_password_change_are_held_on_that_page(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => true]));

        $this->get(Dashboard::getUrl())->assertRedirect(ChangePassword::getUrl());
        $this->get(MyProfile::getUrl())->assertRedirect(ChangePassword::getUrl());
        $this->get(ChangePassword::getUrl())->assertOk();
    }

    public function test_accounts_that_already_chose_a_password_are_not_redirected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(Dashboard::getUrl())->assertOk();
    }

    public function test_production_forces_debug_off_and_flags_seeded_accounts(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();
        (new TeacherSeeder)->run();

        $this->assertFalse(config('app.debug'));
        $this->assertTrue(User::query()->where('must_change_password', false)->doesntExist());
        $this->assertTrue(User::query()->exists());
    }

    public function test_seeded_accounts_are_not_flagged_outside_production(): void
    {
        $this->seed(TeacherSeeder::class);

        $this->assertTrue(User::query()->where('must_change_password', true)->doesntExist());
    }
}
