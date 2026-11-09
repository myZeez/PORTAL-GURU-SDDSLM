<?php

namespace Tests\Feature;

use App\Enums\SchoolDay;
use App\Filament\Pages\JournalSelf;
use App\Models\Classroom;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class JournalMergeTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(User $teacher, Classroom $classroom, Subject $subject, string $start, string $end): Schedule
    {
        return Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'time_slot_id' => TimeSlot::factory()->create(['starts_at' => $start, 'ends_at' => $end])->id,
        ]);
    }

    public function test_back_to_back_slots_of_one_class_and_subject_are_one_choice_and_one_journal(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();
        $first = $this->lesson($teacher, $classroom, $subject, '07:30:00', '08:00:00');
        $second = $this->lesson($teacher, $classroom, $subject, '08:00:00', '08:30:00');

        $this->actingAs($teacher);

        Livewire::test(JournalSelf::class)
            ->fillForm(['date' => '2026-09-14', 'schedule_id' => $first->id, 'topic' => 'Pecahan'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSee('07.30–08.30')
            ->assertSee('2 JP');

        $this->assertSame(2, Journal::query()->where('topic', 'Pecahan')->count());
        $this->assertTrue(Journal::query()->where('schedule_id', $second->id)->exists());
    }

    public function test_a_gap_a_different_class_or_a_different_subject_keeps_journals_apart(): void
    {
        $teacher = User::factory()->create();
        $classroomA = Classroom::factory()->create();
        $classroomB = Classroom::factory()->create();
        $matematika = Subject::factory()->create();
        $ipa = Subject::factory()->create();

        $schedules = new EloquentCollection([
            $this->lesson($teacher, $classroomA, $matematika, '07:30:00', '08:00:00'),
            $this->lesson($teacher, $classroomA, $matematika, '08:30:00', '09:00:00'),
            $this->lesson($teacher, $classroomB, $matematika, '09:00:00', '09:30:00'),
            $this->lesson($teacher, $classroomB, $ipa, '09:30:00', '10:00:00'),
        ]);

        $this->assertCount(4, Schedule::groupConsecutive($schedules));
    }

    public function test_saving_one_slot_of_a_run_leaves_the_rest_to_fill_separately(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();
        $first = $this->lesson($teacher, $classroom, $subject, '07:30:00', '08:00:00');
        $second = $this->lesson($teacher, $classroom, $subject, '08:00:00', '08:30:00');

        Journal::factory()->create(['schedule_id' => $first->id, 'teacher_id' => $teacher->id, 'date' => '2026-09-14', 'topic' => 'Sudah diisi']);

        $this->actingAs($teacher);

        Livewire::test(JournalSelf::class)
            ->fillForm(['date' => '2026-09-14', 'schedule_id' => $second->id, 'topic' => 'Latihan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Journal::query()->count());
        $this->assertSame(1, Journal::query()->where('topic', 'Latihan')->count());
    }
}
