<?php

namespace Tests\Feature;

use App\Enums\SchoolDay;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_classroom_cannot_have_two_schedules_in_the_same_slot(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $matematika = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $matematika->id,
        ]);

        $this->assertTrue(Schedule::hasClassroomConflict(
            $semester->id,
            SchoolDay::Senin,
            $slot->id,
            $classroom->id,
            $matematika,
        ));
    }

    public function test_a_teacher_cannot_teach_two_classes_in_the_same_slot(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $matematika = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $matematika->id,
        ]);

        $this->assertTrue(Schedule::hasTeacherConflict(
            $semester->id,
            SchoolDay::Senin,
            $slot->id,
            $teacher->id,
            $matematika,
        ));
    }

    public function test_editing_a_schedule_does_not_conflict_with_itself(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create();
        $teacher = User::factory()->create();
        $subject = Subject::factory()->create();

        $schedule = Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertFalse(Schedule::hasClassroomConflict(
            $semester->id,
            SchoolDay::Senin,
            $slot->id,
            $classroom->id,
            $subject,
            ignoreId: $schedule->id,
        ));
        $this->assertFalse(Schedule::hasTeacherConflict(
            $semester->id,
            SchoolDay::Senin,
            $slot->id,
            $teacher->id,
            $subject,
            ignoreId: $schedule->id,
        ));
    }

    public function test_pjok_on_kamis_is_exempt_from_both_conflict_checks(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $teacher = User::factory()->create();
        $pjok = Subject::factory()->create(['allows_concurrent_scheduling' => true]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Kamis,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroomA->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $pjok->id,
        ]);

        // Same teacher, same slot, a different classroom — allowed on Kamis for PJOK.
        $this->assertFalse(Schedule::hasTeacherConflict(
            $semester->id,
            SchoolDay::Kamis,
            $slot->id,
            $teacher->id,
            $pjok,
        ));
        $this->assertFalse(Schedule::hasClassroomConflict(
            $semester->id,
            SchoolDay::Kamis,
            $slot->id,
            $classroomB->id,
            $pjok,
        ));
    }

    public function test_pjok_on_a_different_day_is_not_exempt(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $pjok = Subject::factory()->create(['allows_concurrent_scheduling' => true]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $pjok->id,
        ]);

        $this->assertTrue(Schedule::hasTeacherConflict(
            $semester->id,
            SchoolDay::Senin,
            $slot->id,
            $teacher->id,
            $pjok,
        ));
    }

    public function test_matematika_on_kamis_is_not_exempt(): void
    {
        $semester = Semester::factory()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $matematika = Subject::factory()->create(['allows_concurrent_scheduling' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Kamis,
            'time_slot_id' => $slot->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $matematika->id,
        ]);

        $this->assertTrue(Schedule::hasTeacherConflict(
            $semester->id,
            SchoolDay::Kamis,
            $slot->id,
            $teacher->id,
            $matematika,
        ));
    }

    public function test_jp_is_one_for_a_normal_subject_and_zero_for_an_exempt_one(): void
    {
        $teachingSubject = Subject::factory()->create(['counts_toward_teaching_load' => true]);
        $nonTeachingSubject = Subject::factory()->create(['counts_toward_teaching_load' => false]);

        $teachingSchedule = Schedule::factory()->create(['subject_id' => $teachingSubject->id]);
        $nonTeachingSchedule = Schedule::factory()->create(['subject_id' => $nonTeachingSubject->id]);

        $this->assertSame(1, $teachingSchedule->jp);
        $this->assertSame(0, $nonTeachingSchedule->jp);
    }
}
