<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Resources\Schedules\Pages\ManageSchedules;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_schedule(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(ScheduleResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_the_schedule_but_not_create_one(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(ScheduleResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', Schedule::class));
    }

    public function test_teachers_without_an_administrative_role_cannot_open_the_schedule(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ScheduleResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_create_a_schedule_entry(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();
        $teacher = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: [
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin->value,
                'time_slot_id' => $slot->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('schedules', [
            'semester_id' => $semester->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_the_form_rejects_a_classroom_double_booking(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: [
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin->value,
                'time_slot_id' => $slot->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'teacher_id' => User::factory()->create()->id,
            ])
            ->assertHasActionErrors(['classroom_id']);
    }

    public function test_the_form_rejects_a_teacher_double_booking(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $subject = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: [
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin->value,
                'time_slot_id' => $slot->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertHasActionErrors(['teacher_id']);
    }

    public function test_pjok_on_kamis_can_be_double_booked_through_the_form(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $pjok = Subject::factory()->create(['allows_concurrent_scheduling' => true]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Kamis,
            'time_slot_id' => $slot->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $pjok->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: [
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Kamis->value,
                'time_slot_id' => $slot->id,
                'classroom_id' => Classroom::factory()->create()->id,
                'subject_id' => $pjok->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertHasNoActionErrors();
    }
}
