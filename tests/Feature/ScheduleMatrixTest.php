<?php

namespace Tests\Feature;

use App\Enums\Role;
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
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleMatrixTest extends TestCase
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

    private function lesson(Semester $semester, SchoolDay $day, TimeSlot $slot, Classroom $classroom, Subject $subject, User $teacher): Schedule
    {
        return Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => $day,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * The subjects on show in the grid itself, so the assertion is not fooled by the subject
     * names that also appear as options in the filter dropdown.
     *
     * @return list<string>
     */
    private function subjectsShown(Testable $component): array
    {
        return collect($component->instance()->getGrid()['rows'])
            ->flatMap(fn (array $row): array => $row['cells'])
            ->filter(fn (?array $cell): bool => ($cell['type'] ?? null) === 'lesson')
            ->pluck('subject')
            ->values()
            ->all();
    }

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
        $this->lesson($semester, SchoolDay::Senin, $otherSlot, $classroom, $subject, $teacher);

        $this->actingAs(User::factory()->create())
            ->get(ScheduleMatrix::getUrl())
            ->assertOk()
            ->assertSee('Pelita Hidup')
            ->assertSee('I-A')
            ->assertSee('Matematika')
            ->assertSee('AA');
    }

    public function test_a_routine_is_centred_and_styled_apart_from_the_lessons(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create(['starts_at' => '06:00', 'ends_at' => '06:40']);
        $lessonSlot = TimeSlot::factory()->create(['starts_at' => '07:30', 'ends_at' => '08:00']);

        DailyRoutine::factory()->create(['day_of_week' => SchoolDay::Senin, 'time_slot_id' => $slot->id, 'label' => 'Sarapan Makanan Bergizi Gratis']);
        $this->lesson($semester, SchoolDay::Senin, $lessonSlot, Classroom::factory()->create(), Subject::factory()->create(['name' => 'Matematika']), User::factory()->create());

        $this->actingAs(User::factory()->create());

        $html = Livewire::test(ScheduleMatrix::class)->html();

        // The band for the whole-school routine: centred text on its own tint.
        $this->assertMatchesRegularExpression('/<td[^>]*class="[^"]*bg-info-50[^"]*"[^>]*>\s*<div class="[^"]*text-center[^"]*text-info-700[^"]*">Sarapan Makanan Bergizi Gratis<\/div>/', $html);
        // A lesson keeps the plain grey tile, so the two never look alike.
        $this->assertMatchesRegularExpression('/<div[^>]*class="[^"]*bg-gray-100[^"]*"[^>]*>\s*<div[^>]*>\s*Matematika/', $html);
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

        $component = Livewire::test(ScheduleMatrix::class);
        $this->assertSame(['Bahasa Arab'], $this->subjectsShown($component));

        $component->call('selectDay', SchoolDay::Selasa->value);
        $this->assertSame([], $this->subjectsShown($component));
    }

    public function test_it_shows_a_friendly_message_when_no_semester_is_active(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ScheduleMatrix::getUrl())
            ->assertOk()
            ->assertSee('Belum ada semester aktif');
    }

    public function test_the_class_view_shows_one_class_across_the_week_and_flags_empty_slots(): void
    {
        $semester = Semester::factory()->active()->create();
        $mine = Classroom::factory()->create(['code' => '1-AAA']);
        $other = Classroom::factory()->create(['code' => '2-BBB']);
        $teacher = User::factory()->create(['code' => 'AA']);
        $slot = TimeSlot::factory()->create(['starts_at' => '08:00', 'ends_at' => '08:40']);
        $this->lesson($semester, SchoolDay::Selasa, $slot, $mine, Subject::factory()->create(['name' => 'Fikih']), $teacher);
        $this->lesson($semester, SchoolDay::Selasa, $slot, $other, Subject::factory()->create(['name' => 'Akidah']), $teacher);

        $this->actingAs(User::factory()->create());

        Livewire::test(ScheduleMatrix::class)
            ->call('setMatrixView', ScheduleMatrix::VIEW_CLASS)
            ->set('classroomId', (string) $mine->id)
            ->call('selectDay', 0)
            ->assertSee('Fikih')
            ->assertDontSee('Akidah')
            ->assertSee('Selasa')
            ->assertSee('Jumat')
            ->assertSee('Kosong');
    }

    public function test_only_leadership_can_open_the_single_teacher_view(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create(['name' => 'Bu Aminah']);
        $this->lesson($semester, SchoolDay::Senin, TimeSlot::factory()->create(), Classroom::factory()->create(), Subject::factory()->create(['name' => 'Tahfiz']), $teacher);

        $this->actingAs(User::factory()->create());
        Livewire::test(ScheduleMatrix::class)
            ->call('setMatrixView', ScheduleMatrix::VIEW_TEACHER)
            ->assertSet('matrixView', ScheduleMatrix::VIEW_ALL);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());
        Livewire::test(ScheduleMatrix::class)
            ->call('setMatrixView', ScheduleMatrix::VIEW_TEACHER)
            ->assertSet('matrixView', ScheduleMatrix::VIEW_TEACHER)
            ->assertSet('teacherId', (string) $teacher->id)
            ->assertSee('Tahfiz');
    }

    public function test_the_teacher_view_lists_only_that_teachers_lessons(): void
    {
        $semester = Semester::factory()->active()->create();
        $aminah = User::factory()->create(['name' => 'Bu Aminah']);
        $budi = User::factory()->create(['name' => 'Pak Budi']);
        $class = Classroom::factory()->create(['code' => '3-CCC']);
        $this->lesson($semester, SchoolDay::Senin, TimeSlot::factory()->create(), $class, Subject::factory()->create(['name' => 'Tahfiz']), $aminah);
        $this->lesson($semester, SchoolDay::Senin, TimeSlot::factory()->create(), Classroom::factory()->create(), Subject::factory()->create(['name' => 'Seni Rupa']), $budi);

        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(ScheduleMatrix::class)
            ->call('setMatrixView', ScheduleMatrix::VIEW_TEACHER)
            ->set('teacherId', (string) $aminah->id)
            ->call('selectDay', 0)
            ->assertSee('Tahfiz')
            ->assertSee('3-CCC')
            ->assertDontSee('Seni Rupa');
    }

    public function test_the_subject_and_teacher_filters_narrow_the_all_classes_view(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $tahfiz = Subject::factory()->create(['name' => 'Tahfiz']);
        $seni = Subject::factory()->create(['name' => 'Seni Rupa']);
        $this->lesson($semester, SchoolDay::Senin, $slot, Classroom::factory()->create(), $tahfiz, User::factory()->create());
        $this->lesson($semester, SchoolDay::Senin, $slot, Classroom::factory()->create(), $seni, User::factory()->create());

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ScheduleMatrix::class);
        $this->assertEqualsCanonicalizing(['Tahfiz', 'Seni Rupa'], $this->subjectsShown($component));

        $component->set('subjectId', (string) $tahfiz->id);
        $this->assertSame(['Tahfiz'], $this->subjectsShown($component));

        $component->call('resetFilters');
        $this->assertEqualsCanonicalizing(['Tahfiz', 'Seni Rupa'], $this->subjectsShown($component));
    }

    public function test_the_semester_filter_switches_to_another_semesters_data(): void
    {
        $current = Semester::factory()->active()->create(['academic_year' => '2026/2027']);
        $previous = Semester::factory()->create(['academic_year' => '2025/2026']);
        $slot = TimeSlot::factory()->create();
        $this->lesson($current, SchoolDay::Senin, $slot, Classroom::factory()->create(), Subject::factory()->create(['name' => 'Bahasa Arab']), User::factory()->create());
        $this->lesson($previous, SchoolDay::Senin, $slot, Classroom::factory()->create(), Subject::factory()->create(['name' => 'Seni Budaya']), User::factory()->create());

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ScheduleMatrix::class);
        $this->assertSame(['Bahasa Arab'], $this->subjectsShown($component));

        $component->set('semesterId', (string) $previous->id);
        $this->assertSame(['Seni Budaya'], $this->subjectsShown($component));
    }

    public function test_leadership_sees_a_teacher_booked_in_two_classes_at_once_but_teachers_do_not(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create(['name' => 'Bu Aminah']);
        $this->lesson($semester, SchoolDay::Senin, $slot, Classroom::factory()->create(['code' => '1-AAA']), Subject::factory()->create(), $teacher);
        $this->lesson($semester, SchoolDay::Senin, $slot, Classroom::factory()->create(['code' => '2-BBB']), Subject::factory()->create(), $teacher);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());
        Livewire::test(ScheduleMatrix::class)
            ->assertSee('2 slot bentrok')
            ->assertSee('Bu Aminah mengajar di 2 kelas sekaligus');

        $this->actingAs(User::factory()->create());
        Livewire::test(ScheduleMatrix::class)
            ->assertDontSee('slot bentrok')
            ->assertDontSee('mengajar di 2 kelas sekaligus');
    }

    public function test_a_class_with_two_lessons_in_one_slot_is_a_clash(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $class = Classroom::factory()->create(['code' => '4-DDD']);
        $this->lesson($semester, SchoolDay::Senin, $slot, $class, Subject::factory()->create(), User::factory()->create());
        $this->lesson($semester, SchoolDay::Senin, $slot, $class, Subject::factory()->create(), User::factory()->create());

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ScheduleMatrix::class)->assertSee('Kelas 4-DDD punya 2 pelajaran sekaligus');
    }

    public function test_lessons_the_school_runs_in_parallel_on_kamis_are_not_clashes(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $teacher = User::factory()->create();
        $pjok = Subject::factory()->create(['name' => 'PJOK', 'allows_concurrent_scheduling' => true]);
        $this->lesson($semester, SchoolDay::Kamis, $slot, Classroom::factory()->create(), $pjok, $teacher);
        $this->lesson($semester, SchoolDay::Kamis, $slot, Classroom::factory()->create(), $pjok, $teacher);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ScheduleMatrix::class)
            ->assertSee('0 slot bentrok')
            ->assertDontSee('mengajar di 2 kelas sekaligus');
    }

    public function test_a_lesson_placed_on_a_routine_hour_is_a_clash(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        DailyRoutine::factory()->create(['day_of_week' => SchoolDay::Senin, 'time_slot_id' => $slot->id, 'label' => 'Upacara']);
        $this->lesson($semester, SchoolDay::Senin, $slot, Classroom::factory()->create(), Subject::factory()->create(['name' => 'Matematika']), User::factory()->create());

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ScheduleMatrix::class)->assertSee('berada di jam rutinitas sekolah');
    }

    public function test_a_saved_schedule_change_shows_up_on_the_next_render(): void
    {
        $semester = Semester::factory()->active()->create();
        $lesson = $this->lesson($semester, SchoolDay::Senin, TimeSlot::factory()->create(), Classroom::factory()->create(), Subject::factory()->create(['name' => 'Matematika']), User::factory()->create());

        $this->actingAs(User::factory()->create());
        $component = Livewire::test(ScheduleMatrix::class);
        $this->assertSame(['Matematika'], $this->subjectsShown($component));

        $lesson->update(['subject_id' => Subject::factory()->create(['name' => 'Bahasa Inggris'])->id]);

        $component->call('selectDay', SchoolDay::Senin->value);
        $this->assertSame(['Bahasa Inggris'], $this->subjectsShown($component));
    }

    public function test_garbage_filter_values_do_not_break_the_page(): void
    {
        Semester::factory()->active()->create();

        $this->actingAs(User::factory()->create());

        Livewire::test(ScheduleMatrix::class)
            ->set('classroomId', 'abc')
            ->set('teacherId', '9999')
            ->set('subjectId', '-1')
            ->set('semesterId', 'x')
            ->call('selectDay', 99)
            ->call('setMatrixView', 'semua')
            ->assertOk();
    }

    public function test_ummi_for_several_classes_at_once_is_shown_as_tim_ummi_and_never_as_a_clash(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $ummi = Subject::factory()->create(['name' => "Ummi/Al-Qur'an", 'team_name' => 'Tim UMMI', 'counts_toward_teaching_load' => false]);

        foreach (Classroom::factory()->count(3)->create() as $classroom) {
            Schedule::factory()->create([
                'semester_id' => $semester->id,
                'day_of_week' => SchoolDay::Senin,
                'time_slot_id' => $slot->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $ummi->id,
            ]);
        }

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $component = Livewire::test(ScheduleMatrix::class);
        $summary = $component->instance()->summarize($component->instance()->getGrid());

        $this->assertSame(0, $summary['clashes']);
        $this->assertSame([], $component->instance()->getClashDescriptions());
        $component->assertSee('Tim UMMI');
    }

    public function test_the_class_view_names_tim_ummi_as_the_teacher_and_the_teacher_list_skips_it(): void
    {
        $semester = Semester::factory()->active()->create();
        $slot = TimeSlot::factory()->create();
        $classroom = Classroom::factory()->create(['code' => 'II-A']);
        $ummi = Subject::factory()->create(['name' => "Ummi/Al-Qur'an", 'team_name' => 'Tim UMMI', 'counts_toward_teaching_load' => false]);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
            'time_slot_id' => $slot->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $ummi->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $component = Livewire::test(ScheduleMatrix::class)
            ->call('setMatrixView', ScheduleMatrix::VIEW_CLASS)
            ->set('classroomId', (string) $classroom->id);

        $component->assertSee('Tim UMMI');
        $this->assertCount(0, $component->instance()->getTeachers());
    }
}
