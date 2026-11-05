<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Pages\Menu;
use App\Filament\Resources\ExtracurricularAttendances\ExtracurricularAttendanceResource;
use App\Filament\Resources\Extracurriculars\ExtracurricularResource;
use App\Filament\Resources\Extracurriculars\Pages\ManageExtracurriculars;
use App\Models\Extracurricular;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtracurricularAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_user_can_open_the_schedule_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ExtracurricularResource::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_cannot_create_a_schedule_entry(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', Extracurricular::class));
    }

    public function test_the_ekskul_coordinator_can_create_a_schedule_entry_with_assistant_teachers(): void
    {
        $teachers = User::factory()->count(2)->create();

        $this->actingAs(User::factory()->withRoles(Role::KoordinatorEkskul)->create());

        Livewire::test(ManageExtracurriculars::class)
            ->callAction('create', data: [
                'name' => 'Pramuka',
                'day' => SchoolDay::Selasa->value,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'location' => 'Lapangan',
                'assistantTeachers' => $teachers->pluck('id')->all(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('extracurriculars', ['name' => 'Pramuka']);
        $this->assertDatabaseCount('extracurricular_teacher', 2);
    }

    public function test_the_extracurricular_attendance_menu_is_gone_for_everyone(): void
    {
        foreach ([null, Role::SuperAdmin, Role::KoordinatorEkskul, Role::KepalaSekolah] as $role) {
            $user = $role ? User::factory()->withRoles($role)->create() : User::factory()->create();

            $this->actingAs($user);

            $this->assertFalse(ExtracurricularAttendanceResource::canAccess());
            $this->assertFalse(ExtracurricularAttendanceResource::shouldRegisterNavigation());
            $this->get(ExtracurricularAttendanceResource::getUrl())->assertForbidden();
        }
    }

    public function test_the_mobile_menu_no_longer_lists_extracurricular_attendance(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KoordinatorEkskul)->create());

        $labels = collect(Livewire::test(Menu::class)->instance()->getModuleGroups())
            ->flatMap(fn (array $group): array => $group['modules'])
            ->pluck('label');

        $this->assertTrue($labels->contains('Jadwal Ekstrakurikuler'));
        $this->assertFalse($labels->contains('Absensi Ekstrakurikuler'));
    }
}
