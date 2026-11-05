<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Pages\SubstitutionSelf;
use App\Filament\Resources\Journals\Pages\ManageJournals;
use App\Filament\Resources\Substitutions\Pages\ManageSubstitutions;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Models\Journal;
use App\Models\RecordAudit;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class DailyRecordCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 09:00:00'); // a Wednesday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function waka(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create();
    }

    /**
     * A substitution filed by $teacher for one of their own lessons on $date.
     */
    private function filedBy(User $teacher, string $date = '2026-11-04'): Substitution
    {
        $schedule = Schedule::factory()->create([
            'semester_id' => Semester::factory()->active()->create()->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::from(Carbon::parse($date)->dayOfWeekIso),
        ]);

        return Substitution::factory()->create([
            'date' => $date,
            'schedule_id' => $schedule->id,
            'created_by' => $teacher->id,
            'reason' => 'Sakit',
        ]);
    }

    public function test_the_teacher_who_filed_a_substitution_sees_ubah_and_hapus_for_it(): void
    {
        $teacher = User::factory()->create();
        $this->filedBy($teacher);

        $this->actingAs($teacher);

        $html = Livewire::test(SubstitutionSelf::class)->html();

        $this->assertStringContainsString("mountAction('editSubstitution'", $html);
        $this->assertStringContainsString("mountAction('deleteSubstitution'", $html);
    }

    public function test_nobody_else_sees_the_buttons_not_even_the_substitute(): void
    {
        $teacher = User::factory()->create();
        $substitution = $this->filedBy($teacher);

        foreach ([$substitution->substituteTeacher, User::factory()->create()] as $other) {
            $this->actingAs($other);

            $this->assertStringNotContainsString("mountAction('editSubstitution'", Livewire::test(SubstitutionSelf::class)->html());
            $this->assertFalse($other->can('update', $substitution));
            $this->assertFalse($other->can('delete', $substitution));
        }
    }

    public function test_the_filing_teacher_can_change_the_substitute_and_the_new_one_is_told(): void
    {
        $teacher = User::factory()->create();
        $substitution = $this->filedBy($teacher);
        $original = $substitution->substituteTeacher;
        $replacement = User::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(SubstitutionSelf::class)
            ->callAction('editSubstitution', data: [
                'date' => '2026-11-04',
                'schedule_id' => $substitution->schedule_id,
                'substitute_teacher_id' => $replacement->id,
                'reason' => 'Sakit, diganti guru lain',
            ], arguments: ['substitution' => $substitution->id])
            ->assertHasNoActionErrors();

        $this->assertSame($replacement->id, $substitution->fresh()->substitute_teacher_id);
        $this->assertSame(1, $replacement->fresh()->notifications()->count());
        $this->assertSame(1, $original->fresh()->notifications()->count()); // only the one when it was first filed

        $audit = RecordAudit::query()->where('subject_type', Substitution::class)->firstOrFail();

        $this->assertSame($teacher->id, $audit->actor_id);
        $this->assertSame(RecordAudit::ACTION_UPDATED, $audit->action);
        $this->assertArrayHasKey('Guru pengganti', $audit->changes());
        $this->assertSame([$original->name, $replacement->name], $audit->changes()['Guru pengganti']);
        $this->assertSame(['Sakit', 'Sakit, diganti guru lain'], $audit->changes()['Alasan']);
    }

    public function test_editing_without_changing_the_substitute_notifies_nobody_again(): void
    {
        $teacher = User::factory()->create();
        $substitution = $this->filedBy($teacher);

        $this->actingAs($teacher);

        Livewire::test(SubstitutionSelf::class)
            ->callAction('editSubstitution', data: [
                'date' => '2026-11-04',
                'schedule_id' => $substitution->schedule_id,
                'substitute_teacher_id' => $substitution->substitute_teacher_id,
                'reason' => 'Dinas luar',
            ], arguments: ['substitution' => $substitution->id])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $substitution->substituteTeacher->fresh()->notifications()->count());
    }

    public function test_deleting_asks_for_a_reason_and_keeps_it_in_the_history(): void
    {
        $teacher = User::factory()->create();
        $substitution = $this->filedBy($teacher);

        $this->actingAs($teacher);

        Livewire::test(SubstitutionSelf::class)
            ->callAction('deleteSubstitution', data: ['reason' => ''], arguments: ['substitution' => $substitution->id])
            ->assertHasActionErrors(['reason']);

        $this->assertModelExists($substitution);

        Livewire::test(SubstitutionSelf::class)
            ->callAction('deleteSubstitution', data: ['reason' => 'Salah pilih jadwal'], arguments: ['substitution' => $substitution->id])
            ->assertHasNoActionErrors();

        $this->assertModelMissing($substitution);

        $audit = RecordAudit::query()->firstOrFail();

        $this->assertSame(RecordAudit::ACTION_DELETED, $audit->action);
        $this->assertSame('Salah pilih jadwal', $audit->reason);
        $this->assertSame($substitution->id, $audit->subject_id);
        $this->assertSame('Sakit', $audit->before['Alasan']);
    }

    public function test_a_colleague_cannot_change_someone_elses_substitution_even_by_sending_its_id(): void
    {
        $substitution = $this->filedBy(User::factory()->create());

        $this->actingAs(User::factory()->create());

        Livewire::test(SubstitutionSelf::class)
            ->callAction('deleteSubstitution', data: ['reason' => 'Iseng'], arguments: ['substitution' => $substitution->id])
            ->assertForbidden();

        $this->assertModelExists($substitution);
        $this->assertSame(0, RecordAudit::query()->count());
    }

    public function test_after_the_day_has_passed_only_waka_kurikulum_can_still_correct_it(): void
    {
        $teacher = User::factory()->create();
        $past = $this->filedBy($teacher, '2026-11-03');

        $this->assertFalse($teacher->can('update', $past));
        $this->assertFalse($teacher->can('delete', $past));
        $this->assertTrue($this->waka()->can('update', $past));
        $this->assertTrue($this->waka()->can('delete', $past));
    }

    public function test_once_a_journal_was_written_the_teacher_can_no_longer_change_the_substitution(): void
    {
        $teacher = User::factory()->create();
        $substitution = $this->filedBy($teacher);

        Journal::factory()->create(['schedule_id' => $substitution->schedule_id, 'date' => '2026-11-04']);

        $this->assertFalse($teacher->can('update', $substitution));
        $this->assertTrue($this->waka()->can('update', $substitution));
    }

    public function test_waka_kurikulum_corrects_and_deletes_from_the_recap_with_a_trace(): void
    {
        $substitution = $this->filedBy(User::factory()->create(), '2026-11-02');
        $replacement = User::factory()->create();
        $waka = $this->waka();

        $this->actingAs($waka);

        Livewire::test(ManageSubstitutions::class)
            ->callTableAction('edit', $substitution, data: [
                'date' => '2026-11-02',
                'schedule_id' => $substitution->schedule_id,
                'substitute_teacher_id' => $replacement->id,
                'reason' => 'Dikoreksi Waka',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($replacement->id, $substitution->fresh()->substitute_teacher_id);

        Livewire::test(ManageSubstitutions::class)
            ->callTableAction('delete', $substitution, data: ['reason' => 'Data ganda'])
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($substitution);
        $this->assertSame(['updated', 'deleted'], RecordAudit::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame([$waka->id, $waka->id], RecordAudit::query()->orderBy('id')->pluck('actor_id')->all());
    }

    public function test_admin_kurikulum_gets_no_edit_or_delete_on_the_recaps(): void
    {
        $substitution = $this->filedBy(User::factory()->create());
        $journal = Journal::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageSubstitutions::class)
            ->assertTableActionHidden('delete', $substitution)
            ->assertTableActionHidden('edit', $substitution);

        Livewire::test(ManageJournals::class)->assertTableActionHidden('delete', $journal);
    }

    public function test_waka_kurikulum_deletes_a_wrong_journal_after_confirming_with_a_reason(): void
    {
        $journal = Journal::factory()->create(['topic' => 'Materi keliru']);

        $this->actingAs($this->waka());

        Livewire::test(ManageJournals::class)
            ->assertTableActionVisible('delete', $journal)
            ->callTableAction('delete', $journal, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);

        $this->assertModelExists($journal);

        Livewire::test(ManageJournals::class)
            ->callTableAction('delete', $journal, data: ['reason' => 'Jurnal salah kelas']);

        $this->assertModelMissing($journal);

        $audit = RecordAudit::query()->where('subject_type', Journal::class)->firstOrFail();

        $this->assertSame('Jurnal salah kelas', $audit->reason);
        $this->assertSame('Materi keliru', $audit->before['Materi']);
        $this->assertNull($audit->after);
    }

    public function test_waka_kurikulum_deletes_a_wrong_attendance_and_the_attendance_history_keeps_it(): void
    {
        $attendance = TeacherAttendance::factory()->create(['date' => '2026-11-03', 'status' => AttendanceStatus::Alpa]);
        $waka = $this->waka();

        $this->actingAs($waka);

        Livewire::test(ManageTeacherAttendances::class)
            ->set('selectedDate', '2026-11-03')
            ->callTableAction('delete', $attendance, data: ['reason' => 'Guru ternyata hadir'])
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($attendance);

        $log = TeacherAttendanceLog::query()->firstOrFail();

        $this->assertSame(TeacherAttendanceLog::ACTION_DELETED, $log->action);
        $this->assertSame($waka->id, $log->actor_id);
        $this->assertSame($attendance->teacher_id, $log->teacher_id);
        $this->assertSame('alpa', $log->before['status']);
        $this->assertSame('Guru ternyata hadir', $log->reason);
        $this->assertNull($log->teacher_attendance_id);
    }

    public function test_the_change_history_lists_who_changed_what_and_why(): void
    {
        $actor = User::factory()->create(['name' => 'Kiki Fauziah']);

        RecordAudit::factory()->create([
            'subject_type' => Substitution::class,
            'label' => 'I-A — Matematika · 04/11/2026',
            'actor_id' => $actor->id,
            'before' => ['Alasan' => 'Sakit'],
            'after' => ['Alasan' => 'Dinas luar'],
            'reason' => 'Salah tulis',
        ]);
        RecordAudit::factory()->create([
            'subject_type' => Substitution::class,
            'action' => RecordAudit::ACTION_DELETED,
            'label' => 'II-B — IPAS · 03/11/2026',
            'actor_id' => $actor->id,
            'before' => ['Alasan' => 'Izin'],
            'after' => null,
            'reason' => 'Data ganda',
        ]);
        RecordAudit::factory()->create(['subject_type' => Journal::class, 'label' => 'Jurnal lain']);

        $html = view('filament.partials.audit-history', [
            'audits' => RecordAudit::query()->where('subject_type', Substitution::class)->with('actor')->latest('id')->get(),
        ])->render();

        $this->assertStringContainsString('I-A — Matematika', $html);
        $this->assertStringContainsString('Sakit', $html);
        $this->assertStringContainsString('Dinas luar', $html);
        $this->assertStringContainsString('Dihapus', $html);
        $this->assertStringContainsString('Kiki Fauziah', $html);
        $this->assertStringContainsString('Alasan: Data ganda', $html);
        $this->assertStringNotContainsString('Jurnal lain', $html);

        $this->actingAs($this->waka());

        Livewire::test(ManageSubstitutions::class)->assertActionVisible('auditHistory');
        Livewire::test(ManageJournals::class)->assertActionVisible('auditHistory');
    }

    public function test_the_change_history_can_never_be_rewritten(): void
    {
        $audit = RecordAudit::factory()->create();

        try {
            $audit->update(['reason' => 'Diubah diam-diam']);
            $this->fail('An audit entry was edited.');
        } catch (LogicException) {
            $this->assertNull($audit->fresh()->reason);
        }

        $this->expectException(LogicException::class);
        $audit->delete();
    }
}
