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

    /**
     * The form data of one schedule request, with the class and slot lists the form takes.
     *
     * @param  list<int>  $slotIds
     * @param  list<int>  $classroomIds
     * @return array<string, mixed>
     */
    private function formData(Semester $semester, SchoolDay $day, array $slotIds, array $classroomIds, Subject $subject, ?User $teacher): array
    {
        return array_filter([
            'semester_id' => $semester->id,
            'day_of_week' => $day->value,
            'time_slot_ids' => $slotIds,
            'classroom_ids' => $classroomIds,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher?->id,
        ], fn (mixed $value): bool => $value !== null);
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
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$slot->id], [$classroom->id], $subject, $teacher))
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('schedules', [
            'semester_id' => $semester->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_one_submission_saves_every_class_in_every_chosen_slot(): void
    {
        $semester = Semester::factory()->create();
        $slots = [
            TimeSlot::factory()->create(['starts_at' => '07:30:00', 'ends_at' => '08:00:00']),
            TimeSlot::factory()->create(['starts_at' => '08:00:00', 'ends_at' => '08:30:00']),
        ];
        $classrooms = Classroom::factory()->count(2)->create();
        $subject = Subject::factory()->create(['allows_concurrent_scheduling' => true]);
        $teacher = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Kamis, collect($slots)->pluck('id')->all(), $classrooms->pluck('id')->all(), $subject, $teacher))
            ->assertHasNoActionErrors();

        $this->assertSame(4, Schedule::query()->count());

        foreach ($classrooms as $classroom) {
            $this->assertSame(2, Schedule::query()->where('classroom_id', $classroom->id)->count());
        }

        // Two 30-minute slots are two schedule rows, so two teaching hours per class.
        $this->assertSame(4, Schedule::query()->where('teacher_id', $teacher->id)->get()->sum('jp'));
    }

    public function test_two_chosen_slots_for_one_class_count_as_two_teaching_hours(): void
    {
        $semester = Semester::factory()->create();
        $slots = TimeSlot::factory()->count(2)->create();
        $teacher = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Selasa, $slots->pluck('id')->all(), [Classroom::factory()->create()->id], Subject::factory()->create(), $teacher))
            ->assertHasNoActionErrors();

        $this->assertSame(2, Schedule::query()->where('teacher_id', $teacher->id)->get()->sum('jp'));
    }

    public function test_one_clashing_combination_saves_nothing_at_all(): void
    {
        $semester = Semester::factory()->create();
        $free = TimeSlot::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '09:30:00']);
        $taken = TimeSlot::factory()->create(['starts_at' => '09:30:00', 'ends_at' => '10:00:00']);
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $taken->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$free->id, $taken->id], [$classroom->id], $subject, User::factory()->create()))
            ->assertHasActionErrors(['classroom_ids']);

        $this->assertSame(1, Schedule::query()->count());
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
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$slot->id], [$classroom->id], $subject, User::factory()->create()))
            ->assertHasActionErrors(['classroom_ids']);
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
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$slot->id], [Classroom::factory()->create()->id], $subject, $teacher))
            ->assertHasActionErrors(['teacher_id']);
    }

    public function test_one_teacher_cannot_be_given_two_classes_in_the_same_slot_at_once(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $subject = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Rabu, [$slot->id], Classroom::factory()->count(2)->create()->pluck('id')->all(), $subject, User::factory()->create()))
            ->assertHasActionErrors(['teacher_id']);

        $this->assertSame(0, Schedule::query()->count());
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
            ->callAction('create', data: $this->formData($semester, SchoolDay::Kamis, [$slot->id], [Classroom::factory()->create()->id], $pjok, $teacher))
            ->assertHasNoActionErrors();
    }

    public function test_the_same_lesson_cannot_be_saved_twice_even_where_doubling_up_is_allowed(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->create();
        $pjok = Subject::factory()->create(['allows_concurrent_scheduling' => true]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $data = $this->formData($semester, SchoolDay::Kamis, [$slot->id], [$classroom->id], $pjok, $teacher);

        Livewire::test(ManageSchedules::class)->callAction('create', data: $data)->assertHasNoActionErrors();
        Livewire::test(ManageSchedules::class)->callAction('create', data: $data)->assertHasActionErrors(['classroom_ids']);

        $this->assertSame(1, Schedule::query()->count());
    }

    public function test_a_team_subject_fills_the_teacher_in_by_itself_and_fits_several_classes_at_once(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classrooms = Classroom::factory()->count(3)->create();
        $ummi = Subject::factory()->create(['name' => "Ummi/Al-Qur'an", 'team_name' => 'Tim UMMI', 'counts_toward_teaching_load' => false]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        // No teacher is sent, and one passed along anyway is ignored.
        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$slot->id], $classrooms->pluck('id')->all(), $ummi, null))
            ->assertHasNoActionErrors();

        $rows = Schedule::query()->with(['teacher', 'subject'])->get();

        $this->assertCount(3, $rows);
        $this->assertSame([null], $rows->pluck('teacher_id')->unique()->values()->all());
        $this->assertSame(['Tim UMMI'], $rows->pluck('teacher_name')->unique()->values()->all());
        $this->assertSame(0, $rows->sum('jp'));
    }

    public function test_a_teacher_given_to_a_team_subject_is_dropped_on_save(): void
    {
        $ummi = Subject::factory()->create(['team_name' => 'Tim UMMI']);

        $schedule = Schedule::factory()->create(['subject_id' => $ummi->id, 'teacher_id' => User::factory()->create()->id]);

        $this->assertNull($schedule->fresh()->teacher_id);
    }

    public function test_a_team_subject_still_cannot_double_book_a_class(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $ummi = Subject::factory()->create(['team_name' => 'Tim UMMI', 'counts_toward_teaching_load' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->create(['allows_concurrent_scheduling' => false])->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callAction('create', data: $this->formData($semester, SchoolDay::Senin, [$slot->id], [$classroom->id], $ummi, null))
            ->assertHasActionErrors(['classroom_ids']);
    }

    public function test_editing_one_schedule_keeps_the_single_class_and_slot_form(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $newSlot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageSchedules::class)
            ->callTableAction('edit', $schedule, data: ['time_slot_id' => $newSlot->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($newSlot->id, $schedule->fresh()->time_slot_id);
    }
}
