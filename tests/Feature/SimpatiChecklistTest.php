<?php

namespace Tests\Feature;

use App\Enums\CalendarDayStatus;
use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SimpatiChecklist;
use App\Models\CalendarDay;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\SimpatiCheckLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class SimpatiChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Classroom}
     */
    private function pendampingWithClass(string $code = '1-AAA'): array
    {
        $pendamping = User::factory()->create(['name' => 'Bu Pendamping']);
        $classroom = Classroom::factory()->create(['code' => $code, 'assistant_teacher_id' => $pendamping->id]);

        return [$pendamping, $classroom];
    }

    public function test_the_checklist_is_open_to_pendamping_waka_kurikulum_and_super_admin_only(): void
    {
        [$pendamping] = $this->pendampingWithClass();

        foreach ([$pendamping, User::factory()->withRoles(Role::WakaKurikulum)->create(), User::factory()->withRoles(Role::SuperAdmin)->create()] as $user) {
            $this->actingAs($user)->get(SimpatiChecklist::getUrl())->assertOk();
        }

        foreach ([User::factory()->create(), User::factory()->withRoles(Role::KepalaSekolah)->create(), User::factory()->withRoles(Role::AdminKurikulum)->create()] as $user) {
            $this->actingAs($user)->get(SimpatiChecklist::getUrl())->assertForbidden();
        }
    }

    public function test_a_pendamping_sees_only_their_own_class(): void
    {
        [$pendamping] = $this->pendampingWithClass('1-AAA');
        Classroom::factory()->create(['code' => '2-BBB']);

        $this->actingAs($pendamping);

        Livewire::test(SimpatiChecklist::class)
            ->assertSee('1-AAA')
            ->assertDontSee('2-BBB');
    }

    public function test_ticking_saves_who_when_and_the_status_and_unticking_is_logged(): void
    {
        [$pendamping, $classroom] = $this->pendampingWithClass();
        $this->actingAs($pendamping);

        $component = Livewire::test(SimpatiChecklist::class)->call('toggle', '2026-10-26');

        $check = SimpatiCheck::query()->sole();
        $this->assertSame($classroom->id, $check->classroom_id);
        $this->assertSame('2026-10-26', $check->date->toDateString());
        $this->assertTrue($check->is_filled);
        $this->assertSame($pendamping->id, $check->checked_by);

        $component->call('toggle', '2026-10-26');

        $this->assertSame(1, SimpatiCheck::count());
        $this->assertFalse(SimpatiCheck::query()->sole()->is_filled);

        $logs = SimpatiCheckLog::query()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([false, true], [$logs[0]->was_filled, $logs[0]->is_filled]);
        $this->assertSame([true, false], [$logs[1]->was_filled, $logs[1]->is_filled]);
        $this->assertSame($pendamping->id, $logs[0]->actor_id);
    }

    public function test_one_class_and_date_can_never_have_two_records(): void
    {
        [, $classroom] = $this->pendampingWithClass();
        $actor = User::factory()->create();

        SimpatiCheck::record($classroom, Carbon::parse('2026-10-26'), true, $actor);
        SimpatiCheck::record($classroom, Carbon::parse('2026-10-26'), true, $actor);
        SimpatiCheck::record($classroom, Carbon::parse('2026-10-26'), false, $actor);

        $this->assertSame(1, SimpatiCheck::count());
        $this->assertSame(2, SimpatiCheckLog::count());
    }

    public function test_a_pendamping_cannot_tick_another_class_even_by_tampering_with_the_class_id(): void
    {
        [$pendamping, $mine] = $this->pendampingWithClass('1-AAA');
        $other = Classroom::factory()->create(['code' => '2-BBB']);
        $this->actingAs($pendamping);

        Livewire::test(SimpatiChecklist::class)
            ->set('classroomId', (string) $other->id)
            ->call('toggle', '2026-10-26');

        $this->assertDatabaseMissing('simpati_checks', ['classroom_id' => $other->id]);
        $this->assertDatabaseHas('simpati_checks', ['classroom_id' => $mine->id, 'date' => '2026-10-26']);
    }

    public function test_waka_kurikulum_can_tick_any_class(): void
    {
        $this->pendampingWithClass('1-AAA');
        $other = Classroom::factory()->create(['code' => '2-BBB']);
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(SimpatiChecklist::class)
            ->set('classroomId', (string) $other->id)
            ->call('toggle', '2026-10-26');

        $this->assertDatabaseHas('simpati_checks', ['classroom_id' => $other->id, 'is_filled' => true]);
    }

    public function test_future_holiday_weekend_and_garbage_dates_are_refused(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-22', 'status' => CalendarDayStatus::Libur]);
        [$pendamping] = $this->pendampingWithClass();
        $this->actingAs($pendamping);

        foreach (['2026-10-27', '2026-10-22', '2026-10-24'] as $date) {
            Livewire::test(SimpatiChecklist::class)->call('toggle', $date);
        }

        $this->assertSame(0, SimpatiCheck::count());

        Livewire::test(SimpatiChecklist::class)->call('toggle', 'bukan-tanggal')->assertStatus(422);
        Livewire::test(SimpatiChecklist::class)->call('toggle', '2026-02-31')->assertStatus(422);
    }

    public function test_a_holiday_is_not_listed_as_something_to_fill(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-22', 'status' => CalendarDayStatus::Libur]);
        [$pendamping] = $this->pendampingWithClass();
        $this->actingAs($pendamping);

        $dates = Livewire::test(SimpatiChecklist::class)->instance()->getRows()
            ->map(fn (array $row): string => $row['date']->toDateString());

        $this->assertTrue($dates->contains('2026-10-26'));
        $this->assertFalse($dates->contains('2026-10-22'));
        $this->assertFalse($dates->contains('2026-10-24'));
        $this->assertFalse($dates->contains('2026-10-27'));
    }

    public function test_month_navigation_never_goes_past_the_current_month(): void
    {
        [$pendamping] = $this->pendampingWithClass();
        $this->actingAs($pendamping);

        Livewire::test(SimpatiChecklist::class)
            ->assertSet('month', '2026-10')
            ->call('nextMonth')
            ->assertSet('month', '2026-10')
            ->call('previousMonth')
            ->assertSet('month', '2026-09')
            ->call('nextMonth')
            ->assertSet('month', '2026-10');
    }

    public function test_the_monitoring_dashboard_counts_filled_and_missing_classes_from_the_same_data(): void
    {
        [, $a] = $this->pendampingWithClass('1-AAA');
        [, $b] = $this->pendampingWithClass('2-BBB');
        $this->pendampingWithClass('3-CCC');
        $this->pendampingWithClass('4-DDD');
        SimpatiCheck::factory()->create(['classroom_id' => $a->id, 'date' => '2026-10-26']);
        SimpatiCheck::factory()->create(['classroom_id' => $b->id, 'date' => '2026-10-26']);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $summary = Livewire::test(Dashboard::class)->instance()->getSimpatiSummary();

        $this->assertTrue($summary['required']);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['filled']);
        $this->assertSame(2, $summary['missing']);
        $this->assertSame(50, $summary['percentage']);
        $this->assertEqualsCanonicalizing(['3-CCC', '4-DDD'], $summary['unfilled']->pluck('code')->all());

        Livewire::test(Dashboard::class)
            ->assertSee('Pengisian SIMPATI Hari Ini')
            ->assertSee('50%');
    }

    public function test_a_tick_shows_up_on_the_dashboard_right_away(): void
    {
        [$pendamping, $classroom] = $this->pendampingWithClass();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());
        $this->assertSame(0, Livewire::test(Dashboard::class)->instance()->getSimpatiSummary()['filled']);

        $this->actingAs($pendamping);
        Livewire::test(SimpatiChecklist::class)->call('toggle', '2026-10-26');

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());
        $this->assertSame(1, Livewire::test(Dashboard::class)->instance()->getSimpatiSummary()['filled']);
        $this->assertSame($classroom->id, SimpatiCheck::query()->sole()->classroom_id);
    }

    public function test_the_extracurricular_coordinator_and_principal_see_the_progress_but_plain_teachers_do_not(): void
    {
        $this->pendampingWithClass();

        foreach ([Role::KoordinatorEkskul, Role::KepalaSekolah, Role::AdminKurikulum, Role::WakaKurikulum] as $role) {
            $this->actingAs(User::factory()->withRoles($role)->create());
            Livewire::test(Dashboard::class)->assertSee('Pengisian SIMPATI Hari Ini');
        }

        $this->actingAs(User::factory()->create());
        Livewire::test(Dashboard::class)->assertDontSee('Pengisian SIMPATI Hari Ini');
    }

    public function test_a_holiday_is_not_counted_against_any_class(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-26', 'status' => CalendarDayStatus::Libur]);
        $this->pendampingWithClass();
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $summary = Livewire::test(Dashboard::class)->instance()->getSimpatiSummary();

        $this->assertFalse($summary['required']);
        $this->assertSame(0, $summary['missing']);

        Livewire::test(Dashboard::class)->assertSee('bukan hari wajib SIMPATI');
    }

    public function test_class_completion_counts_only_required_dates(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-22', 'status' => CalendarDayStatus::Libur]);
        [, $classroom] = $this->pendampingWithClass();

        foreach (['2026-10-20', '2026-10-21', '2026-10-22'] as $date) {
            SimpatiCheck::factory()->create(['classroom_id' => $classroom->id, 'date' => $date]);
        }

        $completion = SimpatiCheck::completionFor($classroom, Carbon::parse('2026-10-19'), Carbon::parse('2026-10-23'));

        $this->assertSame(['filled' => 2, 'required' => 4], $completion);
    }

    public function test_the_simpati_history_cannot_be_edited(): void
    {
        $log = SimpatiCheckLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->update(['is_filled' => false]);
    }
}
