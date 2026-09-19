<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\DailyRoutines\DailyRoutineResource;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\TimeSlots\TimeSlotResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function masterDataResources(): array
    {
        return [
            'guru & staf' => [UserResource::class],
            'rombel' => [ClassroomResource::class],
            'mata pelajaran' => [SubjectResource::class],
            'jam pelajaran' => [TimeSlotResource::class],
            'rutinitas harian' => [DailyRoutineResource::class],
            'semester' => [SemesterResource::class],
        ];
    }

    #[DataProvider('masterDataResources')]
    public function test_administrators_can_open_master_data_pages(string $resource): void
    {
        $administrator = User::factory()->withRoles(Role::WakaKurikulum)->create();

        $this->actingAs($administrator)
            ->get($resource::getUrl('index'))
            ->assertOk();
    }

    #[DataProvider('masterDataResources')]
    public function test_the_principal_can_read_master_data_but_not_change_it(string $resource): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get($resource::getUrl('index'))
            ->assertOk();

        $this->assertFalse($principal->can('create', $resource::getModel()));
    }

    #[DataProvider('masterDataResources')]
    public function test_teachers_without_an_administrative_role_cannot_open_master_data_pages(string $resource): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get($resource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_deactivated_staff_cannot_enter_the_portal(): void
    {
        $formerTeacher = User::factory()->inactive()->create();

        $this->actingAs($formerTeacher)
            ->get('/')
            ->assertForbidden();
    }

    public function test_administrators_cannot_delete_their_own_account(): void
    {
        $administrator = User::factory()->withRoles(Role::AdminKurikulum)->create();
        $colleague = User::factory()->create();

        $this->assertFalse($administrator->can('delete', $administrator));
        $this->assertTrue($administrator->can('delete', $colleague));
    }

    public function test_an_administrator_can_create_a_staff_account_with_roles(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::Developer)->create());

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'code' => 'bru',
                'name' => 'Guru Baru, S.Pd.',
                'position' => 'Wakil Kepala Bidang Sarana dan Prasarana',
                'email' => 'guru.baru@example.com',
                'password' => 'rahasia-sekali-2026',
                'role' => Role::WakaSarpras->value,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $staff = User::query()->where('email', 'guru.baru@example.com')->firstOrFail();

        $this->assertSame('BRU', $staff->code);
        $this->assertTrue($staff->hasRole(Role::WakaSarpras));
        $this->assertTrue($staff->isAdministrator());
    }

    public function test_the_principal_does_not_get_a_create_button(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(ManageSubjects::class)
            ->assertActionHidden('create');
    }
}
