<?php

namespace Tests\Feature;

use App\Enums\SchoolDay;
use App\Filament\Pages\MySchedule;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_teacher_can_open_their_own_schedule(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MySchedule::getUrl())
            ->assertOk();
    }

    public function test_it_shows_only_the_logged_in_teachers_own_teaching_slots(): void
    {
        $semester = Semester::factory()->active()->create();
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'teacher_id' => $me->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika']),
        ]);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'teacher_id' => $someoneElse->id,
            'subject_id' => Subject::factory()->create(['name' => 'Bahasa Inggris']),
        ]);

        $this->actingAs($me)
            ->get(MySchedule::getUrl())
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Bahasa Inggris');
    }

    public function test_it_shows_whole_school_routines_alongside_teaching_slots(): void
    {
        DailyRoutine::factory()->create([
            'day_of_week' => SchoolDay::Senin,
            'label' => 'Upacara Bendera',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(MySchedule::getUrl())
            ->assertOk()
            ->assertSee('Upacara Bendera')
            ->assertSee('Rutinitas · Semua Kelas');
    }

    public function test_total_jp_sums_only_teaching_subjects(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();

        Schedule::factory()->count(2)->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['counts_toward_teaching_load' => true]),
        ]);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['counts_toward_teaching_load' => false]),
        ]);

        $this->actingAs($teacher);

        Livewire::test(MySchedule::class)
            ->assertSet('day', SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value);

        $component = Livewire::test(MySchedule::class);
        $component->call('selectDay', SchoolDay::Senin->value);

        $this->assertSame(2, $component->instance()->getTotalJp());
    }

    public function test_it_shows_an_empty_state_when_there_is_nothing_scheduled(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MySchedule::getUrl())
            ->assertOk()
            ->assertSee('Tidak ada jadwal untuk hari ini.');
    }
}
