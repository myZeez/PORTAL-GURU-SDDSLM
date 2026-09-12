<?php

namespace Tests\Feature;

use App\Enums\CocurricularActivityType;
use App\Enums\GraduateDimension;
use App\Enums\Role;
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

    public function test_any_signed_in_user_can_open_the_schedule_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(CocurricularScheduleResource::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_cannot_create_a_schedule(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', CocurricularSchedule::class));
    }

    public function test_an_administrator_can_create_a_schedule_with_target_classrooms(): void
    {
        $classrooms = Classroom::factory()->count(2)->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageCocurricularSchedules::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'theme' => 'Kunjungan ke Museum',
                'classrooms' => $classrooms->pluck('id')->all(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('cocurricular_schedules', ['theme' => 'Kunjungan ke Museum']);
        $this->assertDatabaseCount('cocurricular_schedule_classroom', 2);
    }

    public function test_any_signed_in_user_can_open_the_cocurricular_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(CocurricularResource::getUrl())
            ->assertOk();
    }

    public function test_a_teacher_with_no_classroom_cannot_submit_a_cocurricular(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', Cocurricular::class));
    }

    public function test_a_homeroom_teacher_can_submit_a_cocurricular_for_their_classroom(): void
    {
        $waliKelas = User::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $waliKelas->id]);
        $schedule = CocurricularSchedule::factory()->create();

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
        ]);
    }

    public function test_an_assistant_teacher_can_also_submit_for_their_classroom(): void
    {
        $pendamping = User::factory()->create();
        Classroom::factory()->create(['assistant_teacher_id' => $pendamping->id]);

        $this->assertTrue($pendamping->can('create', Cocurricular::class));
    }

    public function test_a_teacher_cannot_edit_another_classrooms_submission(): void
    {
        $outsider = User::factory()->create();
        Classroom::factory()->create(['homeroom_teacher_id' => $outsider->id]);

        $othersClassroom = Classroom::factory()->create(['homeroom_teacher_id' => User::factory()->create()->id]);
        $cocurricular = Cocurricular::factory()->create(['classroom_id' => $othersClassroom->id]);

        $this->assertFalse($outsider->can('update', $cocurricular));
    }
}
