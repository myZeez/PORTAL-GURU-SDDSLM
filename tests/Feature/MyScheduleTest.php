<?php

namespace Tests\Feature;

use App\Enums\SchoolDay;
use App\Filament\Pages\MySchedule;
use App\Models\Classroom;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Substitution;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MyScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_no_view_ever_reveals_a_colleagues_schedule(): void
    {
        $semester = Semester::factory()->active()->create();
        $colleague = User::factory()->create();

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'teacher_id' => $colleague->id,
            'subject_id' => Subject::factory()->create(['name' => 'Bahasa Inggris']),
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(MySchedule::class)
            ->assertDontSee('Bahasa Inggris')
            ->call('setMode', MySchedule::MODE_WEEK)
            ->assertDontSee('Bahasa Inggris')
            ->call('setMode', MySchedule::MODE_DATE)
            ->set('date', '2026-10-26')
            ->assertDontSee('Bahasa Inggris');
    }

    public function test_whole_school_routines_never_appear_in_my_schedule(): void
    {
        DailyRoutine::factory()->create([
            'day_of_week' => SchoolDay::Senin,
            'label' => 'Upacara Bendera',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(MySchedule::class)
            ->assertDontSee('Upacara Bendera')
            ->assertDontSee('Rutinitas')
            ->call('setMode', MySchedule::MODE_WEEK)
            ->assertDontSee('Upacara Bendera')
            ->call('setMode', MySchedule::MODE_DATE)
            ->set('date', '2026-10-26')
            ->assertDontSee('Upacara Bendera');
    }

    public function test_only_my_own_lessons_are_listed_and_the_routine_stays_on_the_matrix(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();

        DailyRoutine::factory()->create(['day_of_week' => SchoolDay::Senin, 'label' => 'Istirahat']);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika']),
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher);

        Livewire::test(MySchedule::class)
            ->set('day', SchoolDay::Senin->value)
            ->assertSee('Matematika')
            ->assertDontSee('Istirahat');
    }

    public function test_an_entry_shows_start_time_end_time_subject_and_class(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => TimeSlot::factory()->create(['starts_at' => '07:30:00', 'ends_at' => '08:10:00']),
            'classroom_id' => Classroom::factory()->create(['code' => 'IV-B', 'name' => 'Al-Khawarizmi']),
            'subject_id' => Subject::factory()->create(['name' => 'Matematika']),
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->get(MySchedule::getUrl())
            ->assertSeeInOrder(['Matematika', 'IV-B', '07.30', '08.10']);
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
            ->assertSet('day', SchoolDay::Senin->value);

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

    public function test_the_week_view_lists_every_day_with_its_own_total(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $subject = Subject::factory()->create(['name' => 'IPAS', 'counts_toward_teaching_load' => true]);

        foreach ([SchoolDay::Senin, SchoolDay::Rabu, SchoolDay::Rabu] as $day) {
            Schedule::factory()->create([
                'semester_id' => $semester->id,
                'day_of_week' => $day,
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
            ]);
        }

        $this->actingAs($teacher);

        $component = Livewire::test(MySchedule::class)->call('setMode', MySchedule::MODE_WEEK);

        $component
            ->assertSee('Senin')
            ->assertSee('Jumat')
            ->assertSee('Tidak ada jadwal pada hari Selasa.')
            ->assertSee('3 JP Mengajar Minggu Ini');

        $this->assertSame(1, $component->instance()->getWeek()[0]['jp']);
        $this->assertSame(2, $component->instance()->getWeek()[2]['jp']);
    }

    public function test_the_date_view_resolves_the_weekday_of_the_picked_date(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Rabu,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['name' => 'Bahasa Arab']),
        ]);

        $this->actingAs($teacher);

        Livewire::test(MySchedule::class)
            ->call('setMode', MySchedule::MODE_DATE)
            ->set('date', '2026-10-28')
            ->assertSee('Bahasa Arab')
            ->set('date', '2026-10-27')
            ->assertDontSee('Bahasa Arab')
            ->assertSee('Tidak ada jadwal pada tanggal ini.');
    }

    public function test_the_date_view_explains_a_weekend_and_ignores_a_garbage_date(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MySchedule::class)
            ->call('setMode', MySchedule::MODE_DATE)
            ->set('date', '2026-10-24')
            ->assertSee('berstatus Libur')
            ->set('date', 'bukan-tanggal')
            ->assertSet('date', '2026-10-26');
    }

    public function test_the_date_view_marks_a_slot_handed_to_a_substitute_and_a_slot_being_covered(): void
    {
        $semester = Semester::factory()->active()->create();
        $me = User::factory()->create(['name' => 'Saya']);
        $colleague = User::factory()->create(['name' => 'Rekan Kerja']);
        $replacement = User::factory()->create(['name' => 'Guru Pengganti']);

        $mine = Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Rabu,
            'teacher_id' => $me->id,
            'subject_id' => Subject::factory()->create(['name' => 'Fikih']),
        ]);
        $theirs = Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Rabu,
            'teacher_id' => $colleague->id,
            'subject_id' => Subject::factory()->create(['name' => 'Tahfiz']),
        ]);

        Substitution::factory()->create(['date' => '2026-10-28', 'schedule_id' => $mine->id, 'substitute_teacher_id' => $replacement->id]);
        Substitution::factory()->create(['date' => '2026-10-28', 'schedule_id' => $theirs->id, 'substitute_teacher_id' => $me->id]);

        $this->actingAs($me);

        Livewire::test(MySchedule::class)
            ->call('setMode', MySchedule::MODE_DATE)
            ->set('date', '2026-10-28')
            ->assertSee('Digantikan oleh Guru Pengganti')
            ->assertSee('Tahfiz')
            ->assertSee('Menggantikan Rekan Kerja');
    }

    public function test_unknown_modes_fall_back_to_the_daily_view(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MySchedule::class)
            ->call('setMode', 'semua-guru')
            ->assertSet('mode', MySchedule::MODE_DAY);
    }
}
