<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\AdditionalDuties\Pages\ManageAdditionalDuties;
use App\Filament\Resources\Journals\Pages\ManageJournals;
use App\Filament\Resources\Schedules\Pages\ManageSchedules;
use App\Filament\Resources\Substitutions\Pages\ManageSubstitutions;
use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Models\AdditionalDuty;
use App\Models\Journal;
use App\Models\RecordAudit;
use App\Models\Schedule;
use App\Models\Substitution;
use App\Models\Task;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function waka(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create(['name' => 'Kiki Fauziah']);
    }

    public function test_waka_kurikulum_clears_many_journals_at_once_and_one_summary_line_is_kept(): void
    {
        $journals = collect(['2026-09-01', '2026-09-15', '2026-10-05'])
            ->map(fn (string $date): Journal => Journal::factory()->create(['date' => $date]));
        $waka = $this->waka();

        $this->actingAs($waka);

        Livewire::test(ManageJournals::class)
            ->callTableBulkAction('bulkDelete', $journals, data: ['reason' => 'Membersihkan data demo'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(0, Journal::query()->count());

        $audit = RecordAudit::query()->sole();

        $this->assertSame(RecordAudit::ACTION_BULK_DELETED, $audit->action);
        $this->assertSame(Journal::class, $audit->subject_type);
        $this->assertNull($audit->subject_id);
        $this->assertSame($waka->id, $audit->actor_id);
        $this->assertSame('Membersihkan data demo', $audit->reason);
        $this->assertSame('3', $audit->before['Jumlah data']);
        $this->assertSame('2026-09-01', $audit->before['Tanggal paling awal']);
        $this->assertSame('2026-10-05', $audit->before['Tanggal paling akhir']);
        $this->assertSame('Hapus massal: 3 jurnal', $audit->label);
    }

    public function test_only_the_ticked_rows_go_and_more_than_a_page_of_them_is_fine(): void
    {
        $journals = Journal::factory()->count(25)->create();
        $keep = $journals->take(5);
        $delete = $journals->slice(5);

        $this->actingAs($this->waka());

        Livewire::test(ManageJournals::class)
            ->callTableBulkAction('bulkDelete', $delete, data: ['reason' => 'Data uji'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(5, Journal::query()->count());
        $this->assertEqualsCanonicalizing($keep->pluck('id')->all(), Journal::query()->pluck('id')->all());
        $this->assertSame('20', RecordAudit::query()->sole()->before['Jumlah data']);
    }

    public function test_a_reason_is_required_and_nothing_is_deleted_without_one(): void
    {
        $journals = Journal::factory()->count(2)->create();

        $this->actingAs($this->waka());

        Livewire::test(ManageJournals::class)
            ->callTableBulkAction('bulkDelete', $journals, data: ['reason' => ''])
            ->assertHasTableBulkActionErrors(['reason']);

        $this->assertSame(2, Journal::query()->count());
        $this->assertSame(0, RecordAudit::query()->count());
    }

    public function test_the_three_daily_record_tables_offer_it_to_waka_kurikulum_but_not_to_admin_kurikulum(): void
    {
        foreach ([[ManageJournals::class, Journal::factory()], [ManageSubstitutions::class, Substitution::factory()], [ManageTeacherAttendances::class, TeacherAttendance::factory()]] as [$page, $factory]) {
            $record = $factory->create();

            $this->actingAs($this->waka());
            Livewire::test($page)->assertTableBulkActionVisible('bulkDelete');

            $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());
            Livewire::test($page)->assertTableBulkActionHidden('bulkDelete');

            $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());
            Livewire::test($page)->assertTableBulkActionHidden('bulkDelete');

            $this->assertModelExists($record);
        }
    }

    public function test_substitutions_and_attendance_can_be_cleared_in_bulk_too(): void
    {
        $substitutions = Substitution::factory()->count(3)->create();
        $attendances = TeacherAttendance::factory()->count(4)->create();

        $this->actingAs($this->waka());

        Livewire::test(ManageSubstitutions::class)
            ->callTableBulkAction('bulkDelete', $substitutions, data: ['reason' => 'Demo'])
            ->assertHasNoTableBulkActionErrors();

        Livewire::test(ManageTeacherAttendances::class)
            ->set('selectedDate', $attendances->first()->date->toDateString())
            ->callTableBulkAction('bulkDelete', $attendances->where('date', $attendances->first()->date), data: ['reason' => 'Demo'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(0, Substitution::query()->count());
        $this->assertLessThan(4, TeacherAttendance::query()->count());
        $this->assertSame(2, RecordAudit::query()->where('action', RecordAudit::ACTION_BULK_DELETED)->count());
    }

    public function test_tasks_duties_and_schedules_can_be_cleared_by_curriculum_management(): void
    {
        $task = Task::factory()->create();
        $task->assignees()->attach(User::factory()->count(2)->create()->pluck('id'));
        $duties = AdditionalDuty::factory()->count(2)->create();
        $schedule = Schedule::factory()->create();
        $journal = Journal::factory()->create(['schedule_id' => $schedule->id, 'teacher_id' => $schedule->teacher_id]);

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageTasks::class)
            ->callTableBulkAction('bulkDelete', [$task], data: ['reason' => 'Tugas demo'])
            ->assertHasNoTableBulkActionErrors();
        Livewire::test(ManageAdditionalDuties::class)
            ->callTableBulkAction('bulkDelete', $duties, data: ['reason' => 'Demo'])
            ->assertHasNoTableBulkActionErrors();
        Livewire::test(ManageSchedules::class)
            ->callTableBulkAction('bulkDelete', [$schedule], data: ['reason' => 'Jadwal demo'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertModelMissing($task);
        $this->assertSame(0, $task->taskAssignees()->count());
        $this->assertSame(0, AdditionalDuty::query()->count());
        $this->assertModelMissing($schedule);
        // The journal of a deleted schedule goes with it, which is why the dialog says so.
        $this->assertModelMissing($journal);
        $this->assertSame(3, RecordAudit::query()->where('action', RecordAudit::ACTION_BULK_DELETED)->count());
    }

    public function test_an_ordinary_teacher_cannot_even_reach_the_tables(): void
    {
        Journal::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('filament.portal.resources.journals.index'))
            ->assertForbidden();
    }

    public function test_the_change_history_shows_the_bulk_deletion_in_words(): void
    {
        RecordAudit::factory()->create([
            'subject_type' => Journal::class,
            'action' => RecordAudit::ACTION_BULK_DELETED,
            'label' => 'Hapus massal: 6.670 jurnal',
            'before' => ['Jumlah data' => '6.670', 'Tanggal paling awal' => '2026-07-13', 'Tanggal paling akhir' => '2026-10-23'],
            'after' => null,
            'reason' => 'Membersihkan data demo',
        ]);

        $html = view('filament.partials.audit-history', [
            'audits' => RecordAudit::query()->where('subject_type', Journal::class)->with('actor')->get(),
        ])->render();

        $this->assertStringContainsString('Dihapus massal', $html);
        $this->assertStringContainsString('6.670', $html);
        $this->assertStringContainsString('2026-07-13', $html);
        $this->assertStringContainsString('Alasan: Membersihkan data demo', $html);
    }
}
