<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\Role;
use App\Filament\Pages\PerformanceDashboard;
use App\Models\Classroom;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\PerformanceSnapshot;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function waka(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create(['name' => 'Kiki Fauziah']);
    }

    /**
     * @return list<int>
     */
    private function rowTeacherIds(mixed $component): array
    {
        return array_map(fn (array $row): int => $row['teacher']->id, $component->instance()->getRows());
    }

    public function test_everyone_can_open_the_page(): void
    {
        foreach ([User::factory()->create(), $this->waka(), User::factory()->withRoles(Role::KepalaSekolah)->create(), User::factory()->withRoles(Role::AdminKurikulum)->create()] as $user) {
            $this->actingAs($user)->get(PerformanceDashboard::getUrl())->assertOk();
        }
    }

    public function test_a_teacher_sees_only_their_own_performance(): void
    {
        $me = User::factory()->create(['name' => 'Saya Sendiri']);
        User::factory()->create(['name' => 'Rekan Yang Lain']);
        $this->actingAs($me);

        $component = Livewire::test(PerformanceDashboard::class);

        $this->assertSame([$me->id], $this->rowTeacherIds($component));
        $component->assertSee('Saya Sendiri')->assertDontSee('Rekan Yang Lain')->assertDontSee('Bandingkan');
    }

    public function test_admin_kurikulum_is_an_ordinary_viewer_of_their_own_data_only(): void
    {
        $admin = User::factory()->withRoles(Role::AdminKurikulum)->create();
        User::factory()->create();
        $this->actingAs($admin);

        $this->assertSame([$admin->id], $this->rowTeacherIds(Livewire::test(PerformanceDashboard::class)));
    }

    public function test_waka_kurikulum_and_the_principal_see_every_active_teacher_but_not_the_principal_or_inactive_staff(): void
    {
        $waka = $this->waka();
        $teacher = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        foreach ([$waka, $principal] as $viewer) {
            $this->actingAs($viewer);
            $ids = $this->rowTeacherIds(Livewire::test(PerformanceDashboard::class));

            $this->assertContains($waka->id, $ids);
            $this->assertContains($teacher->id, $ids);
            $this->assertNotContains($inactive->id, $ids);
            $this->assertNotContains($principal->id, $ids);
        }
    }

    public function test_a_teacher_cannot_open_a_colleagues_detail_or_pick_one_to_compare(): void
    {
        $colleague = User::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(PerformanceDashboard::class)->call('openDetail', $colleague->id)->assertForbidden();
        Livewire::test(PerformanceDashboard::class)->call('toggleCompare', $colleague->id)->assertForbidden();
    }

    public function test_the_open_teacher_cannot_be_swapped_from_the_browser(): void
    {
        $colleague = User::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PerformanceDashboard::class)->set('detailTeacherId', $colleague->id);
    }

    public function test_a_teacher_cannot_use_the_filters_to_see_others(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($me);

        $component = Livewire::test(PerformanceDashboard::class)->set('teacherId', (string) $other->id);

        $this->assertSame([$me->id], $this->rowTeacherIds($component));
    }

    public function test_reviewers_can_filter_by_teacher_class_responsibility_and_target_status(): void
    {
        $waka = $this->waka();
        $wali = User::factory()->create(['name' => 'Bu Wali']);
        $biasa = User::factory()->create(['name' => 'Pak Biasa']);
        $class = Classroom::factory()->create(['homeroom_teacher_id' => $wali->id]);
        TeacherAttendance::factory()->create(['teacher_id' => $wali->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['teacher_id' => $waka->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['teacher_id' => $biasa->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Alpa]);
        $this->actingAs($waka);

        $component = Livewire::test(PerformanceDashboard::class)->set('periodType', 'range')->set('from', '2026-10-27')->set('to', '2026-10-27');

        $component->set('teacherId', (string) $wali->id);
        $this->assertSame([$wali->id], $this->rowTeacherIds($component));

        $component->call('resetFilters')->set('classroomId', (string) $class->id);
        $this->assertSame([$wali->id], $this->rowTeacherIds($component));

        $component->call('resetFilters')->set('responsibility', 'homeroom');
        $this->assertSame([$wali->id], $this->rowTeacherIds($component));

        $component->call('resetFilters')->set('indicatorFilter', 'attendance')->set('statusFilter', 'below');
        $this->assertSame([$biasa->id], $this->rowTeacherIds($component));

        $component->set('statusFilter', 'met');
        $this->assertContains($wali->id, $this->rowTeacherIds($component));
        $this->assertNotContains($biasa->id, $this->rowTeacherIds($component));
    }

    public function test_garbage_period_and_filter_values_do_not_break_the_page(): void
    {
        $this->actingAs($this->waka());

        Livewire::test(PerformanceDashboard::class)
            ->set('periodType', 'selamanya')
            ->set('month', 'bukan-bulan')
            ->set('anchor', 'x')
            ->set('from', '2026-02-31')
            ->set('classroomId', 'abc')
            ->set('indicatorFilter', 'ngawur')
            ->set('statusFilter', 'ngawur')
            ->set('sort', 'ngawur')
            ->assertOk();
    }

    public function test_comparison_holds_at_most_four_teachers_and_can_be_cleared(): void
    {
        $waka = $this->waka();
        $teachers = User::factory()->count(5)->create();
        $this->actingAs($waka);

        $component = Livewire::test(PerformanceDashboard::class);

        foreach ($teachers as $teacher) {
            $component->call('toggleCompare', $teacher->id);
        }

        $this->assertCount(4, $component->instance()->compare);

        $component->call('toggleCompare', $teachers[0]->id);
        $this->assertCount(3, $component->instance()->compare);

        $component->call('clearCompare');
        $this->assertSame([], $component->instance()->compare);
    }

    public function test_the_detail_shows_the_records_behind_the_numbers(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Sakit]);
        $this->actingAs($this->waka());

        $detail = Livewire::test(PerformanceDashboard::class)
            ->set('periodType', 'range')->set('from', '2026-10-26')->set('to', '2026-10-28')
            ->call('openDetail', $teacher->id)
            ->assertSee('Data pendukung setiap angka')
            ->assertSee('Sakit')
            ->instance()->getDetail();

        $this->assertSame(25.0, $detail['indicators']['attendance']['value']);
    }

    public function test_only_waka_kurikulum_sees_the_management_actions(): void
    {
        $this->actingAs($this->waka());
        Livewire::test(PerformanceDashboard::class)
            ->assertActionVisible('settings')->assertActionVisible('saveSnapshot')->assertActionVisible('addFollowUp');

        foreach ([User::factory()->withRoles(Role::KepalaSekolah)->create(), User::factory()->create(), User::factory()->withRoles(Role::AdminKurikulum)->create()] as $user) {
            $this->actingAs($user);
            Livewire::test(PerformanceDashboard::class)
                ->assertActionHidden('settings')->assertActionHidden('saveSnapshot')->assertActionHidden('addFollowUp');
        }
    }

    public function test_scoring_stays_off_until_weights_total_exactly_100(): void
    {
        $this->actingAs($this->waka());
        $weights = ['attendance' => 40, 'journal' => 30, 'tasks' => 10, 'simpati' => 5, 'class_administration' => 5, 'follow_up' => 10];

        Livewire::test(PerformanceDashboard::class)
            ->callAction('settings', data: ['is_scoring_enabled' => true, 'weights' => [...$weights, 'follow_up' => 5], 'target_percentage' => 80, 'thresholds' => []]);

        $this->assertFalse(PerformanceSetting::current()->canScore());

        Livewire::test(PerformanceDashboard::class)
            ->callAction('settings', data: ['is_scoring_enabled' => true, 'weights' => $weights, 'target_percentage' => 85, 'thresholds' => []])
            ->assertHasNoActionErrors();

        $settings = PerformanceSetting::current();
        $this->assertTrue($settings->canScore());
        $this->assertSame(85, $settings->target_percentage);
        $this->assertSame(40, $settings->weightsByIndicator()['attendance']);
    }

    public function test_the_label_cutoffs_must_descend(): void
    {
        $this->actingAs($this->waka());
        $weights = ['attendance' => 100, 'journal' => 0, 'tasks' => 0, 'simpati' => 0, 'class_administration' => 0, 'follow_up' => 0];

        Livewire::test(PerformanceDashboard::class)
            ->callAction('settings', data: ['is_scoring_enabled' => true, 'weights' => $weights, 'target_percentage' => 80, 'thresholds' => ['excellent' => 60, 'good' => 75, 'fair' => 90]]);

        $this->assertNull(PerformanceSetting::current()->thresholds);

        Livewire::test(PerformanceDashboard::class)
            ->callAction('settings', data: ['is_scoring_enabled' => true, 'weights' => $weights, 'target_percentage' => 80, 'thresholds' => ['excellent' => 90, 'good' => 75, 'fair' => 60]]);

        $this->assertSame(['excellent' => 90, 'good' => 75, 'fair' => 60], PerformanceSetting::current()->thresholds);
    }

    public function test_saving_a_period_stores_everyone_once_and_resaving_refreshes_instead_of_duplicating(): void
    {
        $teacher = User::factory()->create();
        $waka = $this->waka();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Hadir]);
        $this->actingAs($waka);

        $component = Livewire::test(PerformanceDashboard::class)
            ->set('periodType', 'range')->set('from', '2026-10-26')->set('to', '2026-10-28');

        $component->callAction('saveSnapshot');

        $this->assertSame(2, PerformanceSnapshot::count());
        $saved = PerformanceSnapshot::query()->where('teacher_id', $teacher->id)->sole();
        $this->assertSame($waka->id, $saved->saved_by);
        $this->assertSame('2026-10-26', $saved->period_start->toDateString());
        $this->assertSame(50.0, (float) $saved->indicators['attendance']['value']);

        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26', 'status' => AttendanceStatus::Hadir]);
        Livewire::test(PerformanceDashboard::class)
            ->set('periodType', 'range')->set('from', '2026-10-26')->set('to', '2026-10-28')
            ->callAction('saveSnapshot');

        $this->assertSame(2, PerformanceSnapshot::count());
        $this->assertSame(100.0, (float) PerformanceSnapshot::query()->where('teacher_id', $teacher->id)->sole()->indicators['attendance']['value']);
    }

    public function test_an_evaluation_note_is_saved_with_the_period_and_survives_a_bulk_resave(): void
    {
        $teacher = User::factory()->create();
        $this->actingAs($this->waka());

        $component = Livewire::test(PerformanceDashboard::class)
            ->set('periodType', 'range')->set('from', '2026-10-26')->set('to', '2026-10-28')
            ->call('openDetail', $teacher->id)
            ->set('evaluationNote', 'Perlu pendampingan administrasi.')
            ->call('saveTeacherSnapshot');

        $this->assertSame('Perlu pendampingan administrasi.', PerformanceSnapshot::query()->where('teacher_id', $teacher->id)->sole()->notes);

        $component->callAction('saveSnapshot');

        $this->assertSame('Perlu pendampingan administrasi.', PerformanceSnapshot::query()->where('teacher_id', $teacher->id)->sole()->notes);
    }

    public function test_only_waka_kurikulum_can_save_history_and_notes(): void
    {
        $teacher = User::factory()->create();
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(PerformanceDashboard::class)
            ->call('openDetail', $teacher->id)
            ->call('saveTeacherSnapshot')
            ->assertForbidden();

        $this->assertSame(0, PerformanceSnapshot::count());
    }

    public function test_waka_kurikulum_records_a_finding_that_becomes_the_teachers_follow_up(): void
    {
        $teacher = User::factory()->create();
        $waka = $this->waka();
        $this->actingAs($waka);

        Livewire::test(PerformanceDashboard::class)
            ->callAction('addFollowUp', data: [
                'teacher_id' => $teacher->id,
                'indicator' => PerformanceIndicator::Journal->value,
                'finding' => 'Jurnal sering terlambat diisi.',
                'recommendation' => 'Isi jurnal di hari yang sama.',
                'responsible_id' => $teacher->id,
                'target_date' => '2026-11-10',
            ])
            ->assertHasNoActionErrors();

        $followUp = PerformanceFollowUp::query()->sole();
        $this->assertSame(PerformanceFollowUpStatus::Open, $followUp->status);
        $this->assertSame($waka->id, $followUp->created_by);

        $this->actingAs($teacher);
        Livewire::test(PerformanceDashboard::class)->assertSee('Jurnal sering terlambat diisi.');
    }

    public function test_a_follow_up_needs_every_field(): void
    {
        $this->actingAs($this->waka());

        Livewire::test(PerformanceDashboard::class)
            ->callAction('addFollowUp', data: ['teacher_id' => User::factory()->create()->id])
            ->assertHasActionErrors(['finding' => 'required', 'recommendation' => 'required', 'responsible_id' => 'required']);
    }

    public function test_follow_up_status_changes_are_for_managers_and_stamp_completion(): void
    {
        $followUp = PerformanceFollowUp::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());
        Livewire::test(PerformanceDashboard::class)->call('setFollowUpStatus', $followUp->id, 'done')->assertForbidden();

        $this->actingAs($this->waka());
        Livewire::test(PerformanceDashboard::class)->call('setFollowUpStatus', $followUp->id, 'done');

        $followUp->refresh();
        $this->assertSame(PerformanceFollowUpStatus::Done, $followUp->status);
        $this->assertNotNull($followUp->completed_at);

        Livewire::test(PerformanceDashboard::class)->call('setFollowUpStatus', $followUp->id, 'open');
        $this->assertNull($followUp->fresh()->completed_at);

        Livewire::test(PerformanceDashboard::class)->call('setFollowUpStatus', $followUp->id, 'ngawur')->assertStatus(422);
    }

    public function test_history_lists_saved_periods_for_the_open_teacher(): void
    {
        $teacher = User::factory()->create();
        PerformanceSnapshot::factory()->create(['teacher_id' => $teacher->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'score' => 82]);
        $this->actingAs($this->waka());

        $history = Livewire::test(PerformanceDashboard::class)->call('openDetail', $teacher->id)->instance()->getHistory();

        $this->assertCount(1, $history);
        $this->assertSame(82, $history->first()->score);
    }

    public function test_the_pdf_for_one_teacher_is_available_to_that_teacher_and_to_reviewers_only(): void
    {
        $teacher = User::factory()->create();
        $colleague = User::factory()->create();
        $query = ['period' => 'month', 'month' => '2026-10', 'teacher' => $teacher->id];

        $this->actingAs($teacher)->get(route('reports.performance.pdf', $query))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($colleague)->get(route('reports.performance.pdf', $query))->assertForbidden();

        $this->actingAs($this->waka())->get(route('reports.performance.pdf', $query))->assertOk();
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())->get(route('reports.performance.pdf', $query))->assertOk();
    }

    public function test_the_summary_pdf_covers_everyone_for_reviewers_and_only_oneself_otherwise(): void
    {
        $this->actingAs($this->waka())
            ->get(route('reports.performance.pdf', ['period' => 'month', 'month' => '2026-10']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::factory()->create())
            ->get(route('reports.performance.pdf', ['period' => 'month', 'month' => '2026-10']))
            ->assertOk();
    }

    public function test_the_pdf_rejects_malformed_parameters(): void
    {
        $this->actingAs($this->waka());

        foreach ([['period' => 'selamanya'], ['month' => '2026-13'], ['from' => '31-10-2026'], ['indicator' => 'ngawur'], ['status' => 'ngawur']] as $params) {
            $this->get(route('reports.performance.pdf', $params))->assertSessionHasErrors();
        }
    }

    public function test_guests_cannot_download_the_pdf(): void
    {
        $this->get(route('reports.performance.pdf'))->assertRedirect();
    }
}
