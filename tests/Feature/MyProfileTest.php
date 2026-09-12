<?php

namespace Tests\Feature;

use App\Enums\DutyType;
use App\Enums\SchoolDay;
use App\Filament\Pages\MyProfile;
use App\Models\AdditionalDuty;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_user_can_open_their_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MyProfile::getUrl())
            ->assertOk();
    }

    public function test_back_to_back_slots_for_the_same_class_and_subject_are_merged(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create(['counts_toward_teaching_load' => true]);

        $slotOne = TimeSlot::factory()->create(['starts_at' => '08:00:00', 'ends_at' => '08:30:00']);
        $slotTwo = TimeSlot::factory()->create(['starts_at' => '08:30:00', 'ends_at' => '09:00:00']);

        foreach ([$slotOne, $slotTwo] as $slot) {
            Schedule::factory()->create([
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin,
                'time_slot_id' => $slot->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);
        }

        $this->actingAs($teacher);

        $weeklySchedule = Livewire::test(MyProfile::class)->instance()->getWeeklySchedule();
        $mondayEntries = $weeklySchedule[SchoolDay::Senin->value]['entries'];

        $this->assertCount(1, $mondayEntries);
        $this->assertSame('08:00:00', $mondayEntries[0]['starts_at']);
        $this->assertSame('09:00:00', $mondayEntries[0]['ends_at']);
        $this->assertSame(2, $mondayEntries[0]['jp']);
    }

    public function test_non_adjacent_slots_are_not_merged(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create(['counts_toward_teaching_load' => true]);

        $slotOne = TimeSlot::factory()->create(['starts_at' => '08:00:00', 'ends_at' => '08:30:00']);
        $slotTwo = TimeSlot::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '09:30:00']);

        foreach ([$slotOne, $slotTwo] as $slot) {
            Schedule::factory()->create([
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin,
                'time_slot_id' => $slot->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);
        }

        $this->actingAs($teacher);

        $weeklySchedule = Livewire::test(MyProfile::class)->instance()->getWeeklySchedule();
        $mondayEntries = $weeklySchedule[SchoolDay::Senin->value]['entries'];

        $this->assertCount(2, $mondayEntries);
    }

    public function test_a_different_subject_in_the_next_slot_is_not_merged(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $slotOne = TimeSlot::factory()->create(['starts_at' => '08:00:00', 'ends_at' => '08:30:00']);
        $slotTwo = TimeSlot::factory()->create(['starts_at' => '08:30:00', 'ends_at' => '09:00:00']);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slotOne->id,
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika']),
            'teacher_id' => $teacher->id,
        ]);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slotTwo->id,
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->create(['name' => 'Bahasa Indonesia']),
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher);

        $weeklySchedule = Livewire::test(MyProfile::class)->instance()->getWeeklySchedule();
        $mondayEntries = $weeklySchedule[SchoolDay::Senin->value]['entries'];

        $this->assertCount(2, $mondayEntries);
    }

    public function test_teaching_load_sums_jp_per_classroom_and_subject_across_the_week(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create(['code' => 'I-A']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'counts_toward_teaching_load' => true]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Rabu,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher);

        $teachingLoad = Livewire::test(MyProfile::class)->instance()->getTeachingLoad();

        $this->assertCount(1, $teachingLoad);
        $this->assertSame(2, $teachingLoad->first()['jp']);
    }

    public function test_total_jp_combines_teaching_load_and_additional_duties(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['counts_toward_teaching_load' => true]),
        ]);
        AdditionalDuty::factory()->create([
            'teacher_id' => $teacher->id,
            'type' => DutyType::Tambahan,
            'jp' => 3,
        ]);

        $this->actingAs($teacher);

        $totalJp = Livewire::test(MyProfile::class)->instance()->getTotalJp();

        $this->assertSame(4, $totalJp);
    }

    public function test_a_teacher_only_sees_their_own_additional_duties(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        AdditionalDuty::factory()->create(['teacher_id' => $me->id, 'title' => 'Kepala Perpustakaan']);
        AdditionalDuty::factory()->create(['teacher_id' => $someoneElse->id, 'title' => 'Wali Kelas']);

        $this->actingAs($me)
            ->get(MyProfile::getUrl())
            ->assertOk()
            ->assertSee('Kepala Perpustakaan')
            ->assertDontSee('Wali Kelas');
    }
}
