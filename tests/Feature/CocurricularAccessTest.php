<?php

namespace Tests\Feature;

use App\Enums\CocurricularActivityType;
use App\Enums\GraduateDimension;
use App\Enums\Role;
use App\Filament\Pages\Menu;
use App\Filament\Resources\Cocurriculars\CocurricularResource;
use App\Filament\Resources\Cocurriculars\Pages\ManageCocurriculars;
use App\Filament\Resources\CocurricularSchedules\CocurricularScheduleResource;
use App\Filament\Resources\CocurricularSchedules\Pages\ManageCocurricularSchedules;
use App\Models\Classroom;
use App\Models\Cocurricular;
use App\Models\CocurricularSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CocurricularAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Users who have no business with kokurikuler.
     *
     * @return array<string, User>
     */
    private function outsiders(): array
    {
        $pendamping = User::factory()->create();
        Classroom::factory()->create(['assistant_teacher_id' => $pendamping->id]);

        return [
            'guru biasa' => User::factory()->create(),
            'pendamping wali kelas' => $pendamping,
            'admin kurikulum' => User::factory()->withRoles(Role::AdminKurikulum)->create(),
            'kepala sekolah' => User::factory()->withRoles(Role::KepalaSekolah)->create(),
            'waka sarpras' => User::factory()->withRoles(Role::WakaSarpras)->create(),
            'koordinator ekskul' => User::factory()->withRoles(Role::KoordinatorEkskul)->create(),
        ];
    }

    private function waliKelas(): User
    {
        $waliKelas = User::factory()->create();
        Classroom::factory()->create(['homeroom_teacher_id' => $waliKelas->id]);

        return $waliKelas;
    }

    /**
     * @return list<string>
     */
    private function menuLabelsFor(User $user): array
    {
        $this->actingAs($user);

        return collect(Livewire::test(Menu::class)->instance()->getModuleGroups())
            ->flatMap(fn (array $group): array => $group['modules'])
            ->pluck('label')
            ->all();
    }

    public function test_the_kokurikuler_menus_are_open_to_waka_kurikulum_and_the_wali_kelas_only(): void
    {
        foreach ([User::factory()->withRoles(Role::WakaKurikulum)->create(), User::factory()->withRoles(Role::SuperAdmin)->create(), $this->waliKelas()] as $user) {
            $this->actingAs($user);

            $this->get(CocurricularScheduleResource::getUrl())->assertOk();
            $this->get(CocurricularResource::getUrl())->assertOk();
        }

        foreach ($this->outsiders() as $label => $user) {
            $this->actingAs($user);

            $this->get(CocurricularScheduleResource::getUrl())->assertForbidden();
            $this->get(CocurricularResource::getUrl())->assertForbidden();
            $this->assertFalse(CocurricularResource::canAccess(), "{$label} still gets the menu");
        }
    }

    public function test_the_mobile_menu_lists_kokurikuler_only_for_those_who_may_open_it(): void
    {
        $this->assertContains('Kokurikuler', $this->menuLabelsFor($this->waliKelas()));
        $this->assertContains('Jadwal Kokurikuler', $this->menuLabelsFor(User::factory()->withRoles(Role::WakaKurikulum)->create()));

        foreach ($this->outsiders() as $user) {
            $labels = $this->menuLabelsFor($user);

            $this->assertNotContains('Kokurikuler', $labels);
            $this->assertNotContains('Jadwal Kokurikuler', $labels);
        }
    }

    public function test_only_waka_kurikulum_sets_the_schedule_and_may_pick_several_classes_at_once(): void
    {
        $classrooms = Classroom::factory()->count(2)->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageCocurricularSchedules::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'theme' => 'Kunjungan ke Museum',
                'classrooms' => $classrooms->pluck('id')->all(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('cocurricular_schedules', ['theme' => 'Kunjungan ke Museum']);
        $this->assertDatabaseCount('cocurricular_schedule_classroom', 2);

        foreach ([...$this->outsiders(), 'wali kelas' => $this->waliKelas()] as $user) {
            $this->assertFalse($user->can('create', CocurricularSchedule::class));
        }
    }

    public function test_a_teacher_with_no_classroom_cannot_submit_a_cocurricular(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', Cocurricular::class));
    }

    public function test_a_homeroom_teacher_can_submit_the_three_things_for_their_classroom(): void
    {
        $waliKelas = User::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $waliKelas->id]);
        $schedule = CocurricularSchedule::factory()->create();
        $schedule->classrooms()->attach($classroom);

        $this->actingAs($waliKelas);

        Livewire::test(ManageCocurriculars::class)
            ->callAction('create', data: [
                'cocurricular_schedule_id' => $schedule->id,
                'classroom_id' => $classroom->id,
                'activity_type' => CocurricularActivityType::Proyek->value,
                'description' => 'Membuat prakarya daur ulang',
                'dimensions' => [GraduateDimension::Kreativitas->value, GraduateDimension::Kolaborasi->value],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('cocurriculars', [
            'classroom_id' => $classroom->id,
            'created_by' => $waliKelas->id,
            'description' => 'Membuat prakarya daur ulang',
        ]);
    }

    public function test_the_activity_type_description_and_dimensions_are_all_required(): void
    {
        $waliKelas = User::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $waliKelas->id]);
        $schedule = CocurricularSchedule::factory()->create();
        $schedule->classrooms()->attach($classroom);

        $this->actingAs($waliKelas);

        Livewire::test(ManageCocurriculars::class)
            ->callAction('create', data: [
                'cocurricular_schedule_id' => $schedule->id,
                'classroom_id' => $classroom->id,
                'activity_type' => null,
                'description' => '',
                'dimensions' => [],
            ])
            ->assertHasActionErrors(['activity_type', 'description', 'dimensions']);
    }

    public function test_a_wali_kelas_cannot_file_for_someone_elses_class_or_a_schedule_not_meant_for_theirs(): void
    {
        $waliKelas = User::factory()->create();
        $mine = Classroom::factory()->create(['homeroom_teacher_id' => $waliKelas->id]);
        $others = Classroom::factory()->create(['homeroom_teacher_id' => User::factory()->create()->id]);
        $scheduleForOthers = CocurricularSchedule::factory()->create();
        $scheduleForOthers->classrooms()->attach($others);
        $scheduleForMine = CocurricularSchedule::factory()->create();
        $scheduleForMine->classrooms()->attach($mine);

        $this->actingAs($waliKelas);

        $data = [
            'activity_type' => CocurricularActivityType::Proyek->value,
            'description' => 'Kegiatan',
            'dimensions' => [GraduateDimension::Kreativitas->value],
        ];

        Livewire::test(ManageCocurriculars::class)
            ->callAction('create', data: [...$data, 'cocurricular_schedule_id' => $scheduleForMine->id, 'classroom_id' => $others->id])
            ->assertHasActionErrors(['classroom_id']);

        Livewire::test(ManageCocurriculars::class)
            ->callAction('create', data: [...$data, 'cocurricular_schedule_id' => $scheduleForOthers->id, 'classroom_id' => $mine->id])
            ->assertHasActionErrors(['cocurricular_schedule_id']);

        $this->assertDatabaseCount('cocurriculars', 0);
    }

    public function test_a_pendamping_can_no_longer_submit_a_cocurricular(): void
    {
        $pendamping = User::factory()->create();
        Classroom::factory()->create(['assistant_teacher_id' => $pendamping->id]);

        $this->assertFalse($pendamping->can('create', Cocurricular::class));
    }

    public function test_a_teacher_cannot_edit_another_classrooms_submission(): void
    {
        $outsider = $this->waliKelas();

        $othersClassroom = Classroom::factory()->create(['homeroom_teacher_id' => User::factory()->create()->id]);
        $cocurricular = Cocurricular::factory()->create(['classroom_id' => $othersClassroom->id]);

        $this->assertFalse($outsider->can('update', $cocurricular));
    }
}
