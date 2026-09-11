<?php

namespace Tests\Feature;

use App\Enums\SchoolDay;
use App\Filament\Pages\ScheduleMatrix;
use App\Models\Classroom;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_teacher_can_open_the_matrix(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ScheduleMatrix::getUrl())
            ->assertOk();
    }

    public function test_it_shows_a_routine_as_one_band_and_a_teaching_slot_per_classroom(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create(['starts_at' => '07:00', 'ends_at' => '07:30']);
        $classroom = Classroom::factory()->create(['code' => 'I-A']);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $teacher = User::factory()->create(['code' => 'AA', 'name' => 'Ara Aulia Nada']);

        DailyRoutine::factory()->create([
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'label' => 'Pelita Hidup',
        ]);

        $otherSlot = TimeSlot::factory()->create(['starts_at' => '08:30', 'ends_at' => '09:00']);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $otherSlot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(ScheduleMatrix::getUrl())
            ->assertOk()
            ->assertSee('Pelita Hidup')
            ->assertSee('I-A')
            ->assertSee('Matematika')
            ->assertSee('AA');
    }

    public function test_switching_the_day_changes_which_schedules_show(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'subject_id' => Subject::factory()->create(['name' => 'Bahasa Arab']),
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ScheduleMatrix::class)
            ->assertSee('Bahasa Arab')
            ->call('selectDay', SchoolDay::Selasa->value)
            ->assertDontSee('Bahasa Arab');
    }

    public function test_it_shows_a_friendly_message_when_no_semester_is_active(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ScheduleMatrix::getUrl())
            ->assertOk()
            ->assertSee('Belum ada semester aktif');
    }
}
