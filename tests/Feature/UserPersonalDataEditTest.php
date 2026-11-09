<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\RecordAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserPersonalDataEditTest extends TestCase
{
    use RefreshDatabase;

    private function kiki(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create();
    }

    public function test_waka_kurikulum_can_correct_a_teachers_personal_data(): void
    {
        $teacher = User::factory()->create(['name' => 'Nama Salah', 'position' => 'Guru', 'phone' => '0800']);

        $this->actingAs($this->kiki());

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $teacher, data: ['name' => 'Nama Benar, S.Pd.', 'email' => $teacher->email, 'position' => 'Wali Kelas II A', 'phone' => '08123456789'])
            ->assertHasNoTableActionErrors();

        $teacher->refresh();

        $this->assertSame('Nama Benar, S.Pd.', $teacher->name);
        $this->assertSame('Wali Kelas II A', $teacher->position);
        $this->assertSame('08123456789', $teacher->phone);
    }

    public function test_waka_kurikulum_can_change_login_role_and_active_switch(): void
    {
        $teacher = User::factory()->create(['email' => 'asli@guru.com']);

        $this->actingAs($this->kiki());

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $teacher, data: [
                'name' => $teacher->name,
                'email' => 'diganti@guru.com',
                'password' => 'RahasiaBaru-12345',
                'role' => Role::AdminKurikulum->value,
                'is_active' => false,
            ])
            ->assertHasNoTableActionErrors();

        $teacher->refresh();

        $this->assertSame('diganti@guru.com', $teacher->email);
        $this->assertTrue(Hash::check('RahasiaBaru-12345', $teacher->password));
        $this->assertTrue($teacher->must_change_password);
        $this->assertSame(Role::AdminKurikulum, $teacher->role);
        $this->assertFalse($teacher->is_active);
    }

    public function test_every_account_change_is_written_to_the_history_without_the_password(): void
    {
        $teacher = User::factory()->create();
        $kiki = $this->kiki();

        $this->actingAs($kiki);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $teacher, data: ['name' => $teacher->name, 'email' => $teacher->email, 'password' => 'RahasiaBaru-12345', 'is_active' => false]);

        $audit = RecordAudit::query()->where('subject_type', User::class)->where('subject_id', $teacher->id)->sole();

        $this->assertSame($kiki->id, $audit->actor_id);
        $this->assertSame(['Status' => ['Aktif', 'Tidak aktif'], 'Password' => ['—', 'Diganti']], $audit->changes());
        $this->assertStringNotContainsString('RahasiaBaru', json_encode($audit->toArray()));
    }

    public function test_waka_kurikulum_can_create_an_account_that_must_change_its_password(): void
    {
        $this->actingAs($this->kiki());

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Guru Baru', 'email' => 'baru@guru.com', 'password' => 'Sementara-12345', 'role' => Role::AdminKurikulum->value, 'is_active' => true])
            ->assertHasNoActionErrors();

        $created = User::query()->where('email', 'baru@guru.com')->sole();

        $this->assertTrue($created->must_change_password);
        $this->assertSame(Role::AdminKurikulum, $created->role);
    }

    public function test_waka_kurikulum_cannot_hand_out_or_touch_super_admin(): void
    {
        $superAdmin = User::factory()->withRoles(Role::SuperAdmin)->create();
        $teacher = User::factory()->create();
        $kiki = $this->kiki();

        $this->assertFalse($kiki->can('update', $superAdmin));

        $this->actingAs($kiki);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Calon Admin', 'email' => 'calon@guru.com', 'password' => 'Sementara-12345', 'role' => Role::SuperAdmin->value])
            ->assertHasActionErrors(['role']);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $teacher, data: ['name' => $teacher->name, 'email' => $teacher->email, 'role' => Role::SuperAdmin->value])
            ->assertHasTableActionErrors(['role']);

        $this->assertNull($teacher->fresh()->role);
    }

    public function test_nobody_can_deactivate_or_demote_their_own_account(): void
    {
        $kiki = $this->kiki();

        $this->actingAs($kiki);

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $kiki, data: ['name' => $kiki->name, 'email' => $kiki->email, 'role' => null, 'is_active' => false]);

        $kiki->refresh();

        $this->assertSame(Role::WakaKurikulum, $kiki->role);
        $this->assertTrue($kiki->is_active);
    }

    public function test_removing_accounts_stays_with_super_admin(): void
    {
        $teacher = User::factory()->create();

        $this->assertFalse($this->kiki()->can('delete', $teacher));
        $this->assertTrue(User::factory()->withRoles(Role::SuperAdmin)->create()->can('delete', $teacher));
        $this->assertFalse(User::factory()->withRoles(Role::AdminKurikulum)->create()->can('update', $teacher));
    }

    public function test_the_photo_column_uses_the_address_that_works_without_a_storage_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/foto.jpg', 'isi-gambar');
        $teacher = User::factory()->create(['photo_path' => 'avatars/foto.jpg']);

        $this->actingAs($this->kiki());

        $html = Livewire::test(ManageUsers::class)->html();

        $this->assertStringContainsString(route('avatars.show', $teacher), $html);
        $this->assertStringNotContainsString('/media/avatars/foto.jpg', $html);
    }

    public function test_uploaded_photos_can_be_opened_by_their_storage_address_but_only_when_signed_in(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/foto.jpg', 'isi-gambar');

        $this->get('/media/avatars/foto.jpg')->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get('/media/avatars/foto.jpg')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/media/avatars/tidak-ada.jpg')->assertNotFound();
    }

    public function test_the_storage_address_cannot_reach_files_outside_the_avatars_folder(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('rahasia.txt', 'jangan dibuka');

        $this->actingAs(User::factory()->create());

        $this->get('/media/avatars/..%2Frahasia.txt')->assertNotFound();
        $this->get('/media/avatars/../rahasia.txt')->assertNotFound();
    }
}
