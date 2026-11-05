<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\Role;
use App\Filament\Pages\AttendanceSelf;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Models\AttendanceReopening;
use App\Models\CalendarDay;
use App\Models\CalendarDayLog;
use App\Models\Semester;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
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

    private function waka(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create(['name' => 'Kiki Fauziah']);
    }

    public function test_only_waka_kurikulum_and_super_admin_manage_attendance_for_other_teachers(): void
    {
        $record = TeacherAttendance::factory()->create();

        foreach ([Role::WakaKurikulum, Role::SuperAdmin] as $role) {
            $user = User::factory()->withRoles($role)->create();
            $this->assertTrue($user->can('create', TeacherAttendance::class));
            $this->assertTrue($user->can('update', $record));
            // Removing a record that was filled in wrongly goes with it, and is always logged.
            $this->assertTrue($user->can('delete', $record));
        }

        foreach ([null, Role::AdminKurikulum, Role::KepalaSekolah, Role::WakaSarpras, Role::KoordinatorEkskul] as $role) {
            $user = $role === null ? User::factory()->create() : User::factory()->withRoles($role)->create();
            $this->assertFalse($user->can('create', TeacherAttendance::class));
            $this->assertFalse($user->can('update', $record));
            $this->assertFalse($user->can('delete', $record));
        }
    }

    public function test_waka_kurikulum_fills_a_missing_day_for_a_teacher_and_it_is_logged(): void
    {
        $teacher = User::factory()->create();
        $waka = $this->waka();
        $this->actingAs($waka);

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: [
                'teacher_id' => $teacher->id,
                'date' => '2026-10-23',
                'status' => AttendanceStatus::Sakit->value,
                'change_reason' => 'Guru lupa mengisi, ada surat dokter.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'date' => '2026-10-23', 'status' => 'sakit', 'created_by' => $waka->id]);

        $log = TeacherAttendanceLog::query()->sole();
        $this->assertSame($waka->id, $log->actor_id);
        $this->assertSame($teacher->id, $log->teacher_id);
        $this->assertSame('2026-10-23', $log->date->toDateString());
        $this->assertSame(TeacherAttendanceLog::ACTION_CREATED, $log->action);
        $this->assertNull($log->before);
        $this->assertSame('sakit', $log->after['status']);
        $this->assertSame('Guru lupa mengisi, ada surat dokter.', $log->reason);
    }

    public function test_a_reason_is_required_for_filling_on_behalf(): void
    {
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: [
                'teacher_id' => User::factory()->create()->id,
                'date' => '2026-10-23',
                'status' => AttendanceStatus::Hadir->value,
            ])
            ->assertHasActionErrors(['change_reason' => 'required']);

        $this->assertSame(0, TeacherAttendance::count());
    }

    public function test_correcting_an_existing_record_needs_confirmation_and_keeps_the_old_status_in_the_history(): void
    {
        $teacher = User::factory()->create();
        $record = TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-23', 'status' => AttendanceStatus::Alpa]);
        $this->actingAs($this->waka());

        $component = Livewire::test(ManageTeacherAttendances::class)
            ->call('selectDate', '2026-10-23')
            ->callTableAction('edit', $record, data: [
                'status' => AttendanceStatus::Izin->value,
                'change_reason' => 'Ternyata ada surat izin.',
            ])
            ->assertHasTableActionErrors(['confirm_change']);

        $this->assertSame(AttendanceStatus::Alpa, $record->fresh()->status);

        Livewire::test(ManageTeacherAttendances::class)
            ->call('selectDate', '2026-10-23')
            ->callTableAction('edit', $record, data: [
                'status' => AttendanceStatus::Izin->value,
                'change_reason' => 'Ternyata ada surat izin.',
                'confirm_change' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(AttendanceStatus::Izin, $record->fresh()->status);

        $log = TeacherAttendanceLog::query()->sole();
        $this->assertSame(TeacherAttendanceLog::ACTION_CORRECTED, $log->action);
        $this->assertSame('alpa', $log->before['status']);
        $this->assertSame('izin', $log->after['status']);
        $this->assertSame('Ternyata ada surat izin.', $log->reason);
    }

    public function test_creating_over_an_existing_record_asks_for_confirmation_first(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-23', 'status' => AttendanceStatus::Hadir]);
        $this->actingAs($this->waka());

        $data = [
            'teacher_id' => $teacher->id,
            'date' => '2026-10-23',
            'status' => AttendanceStatus::Cuti->value,
            'leave_starts_on' => '2026-10-23',
            'leave_ends_on' => '2026-10-24',
            'change_reason' => 'Cuti disetujui kepala sekolah.',
        ];

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: $data)
            ->assertHasActionErrors(['confirm_change']);

        $this->assertSame(AttendanceStatus::Hadir, TeacherAttendance::query()->sole()->status);

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: [...$data, 'confirm_change' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(1, TeacherAttendance::count());
        $this->assertSame(AttendanceStatus::Cuti, TeacherAttendance::query()->sole()->status);
        $this->assertSame('hadir', TeacherAttendanceLog::query()->sole()->before['status']);
    }

    public function test_a_date_with_no_attendance_duty_cannot_be_filled(): void
    {
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: [
                'teacher_id' => User::factory()->create()->id,
                'date' => '2026-10-24',
                'status' => AttendanceStatus::Hadir->value,
                'change_reason' => 'Coba isi hari Sabtu.',
            ])
            ->assertHasActionErrors(['date']);
    }

    public function test_the_attendance_history_cannot_be_edited_or_deleted(): void
    {
        $log = TeacherAttendanceLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->update(['reason' => 'diubah']);
    }

    public function test_the_attendance_history_cannot_be_deleted(): void
    {
        $log = TeacherAttendanceLog::factory()->create();

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_only_people_who_oversee_attendance_can_read_the_history(): void
    {
        foreach ([Role::WakaKurikulum, Role::AdminKurikulum, Role::SuperAdmin, Role::KepalaSekolah] as $role) {
            $this->assertTrue(User::factory()->withRoles($role)->create()->can('viewAny', TeacherAttendanceLog::class));
        }

        $this->assertFalse(User::factory()->create()->can('viewAny', TeacherAttendanceLog::class));
        $this->assertFalse(User::factory()->withRoles(Role::KoordinatorEkskul)->create()->can('viewAny', TeacherAttendanceLog::class));
    }

    public function test_teachers_still_see_their_own_attendance_and_cannot_edit_a_saved_day(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26', 'status' => AttendanceStatus::Hadir]);
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->assertSee('Absensi hari ini sudah tersimpan')
            ->assertSee('Terkunci');
    }

    public function test_a_holiday_with_an_explanation_is_logged_and_can_be_reverted(): void
    {
        Semester::factory()->active()->create();
        $waka = $this->waka();
        $this->actingAs($waka);

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('manageDate', data: [
                'date' => '2026-10-28',
                'status' => CalendarDayStatus::Libur->value,
                'notes' => 'Cuti bersama.',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(CalendarDayStatus::Libur, CalendarDay::statusFor('2026-10-28'));
        $this->assertFalse(TeacherAttendance::isFillableDate(Carbon::parse('2026-10-28')));

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('manageDate', data: [
                'date' => '2026-10-28',
                'status' => CalendarDayStatus::Efektif->value,
                'notes' => 'Cuti bersama dibatalkan.',
            ]);

        $this->assertSame(CalendarDayStatus::Efektif, CalendarDay::statusFor('2026-10-28'));

        $logs = CalendarDayLog::query()->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['efektif', 'libur'], [$logs[0]->before_status, $logs[0]->after_status]);
        $this->assertSame('Cuti bersama.', $logs[0]->notes);
        $this->assertSame(['libur', 'efektif'], [$logs[1]->before_status, $logs[1]->after_status]);
        $this->assertSame($waka->id, $logs[1]->actor_id);
    }

    public function test_a_calendar_change_needs_an_explanation(): void
    {
        Semester::factory()->active()->create();
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('manageDate', data: ['date' => '2026-10-28', 'status' => CalendarDayStatus::Libur->value])
            ->assertHasActionErrors(['notes' => 'required']);
    }

    public function test_changes_made_with_nobody_signed_in_are_not_logged(): void
    {
        $semester = Semester::factory()->active()->create();

        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-12-25', 'status' => CalendarDayStatus::Libur]);

        $this->assertSame(0, CalendarDayLog::count());
    }

    public function test_an_official_activity_on_a_weekend_requires_attendance_but_a_plain_weekend_does_not(): void
    {
        $semester = Semester::factory()->active()->create();
        $saturday = Carbon::parse('2026-10-24');

        $this->assertFalse(TeacherAttendance::isFillableDate($saturday));

        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-24', 'status' => CalendarDayStatus::KegiatanKhusus]);
        $this->assertTrue(TeacherAttendance::isFillableDate($saturday));

        CalendarDay::query()->whereDate('date', '2026-10-24')->first()->update(['status' => CalendarDayStatus::Libur]);
        $this->assertFalse(TeacherAttendance::isFillableDate($saturday));
    }

    public function test_curriculum_staff_without_attendance_authority_do_not_see_the_management_actions(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageTeacherAttendances::class)
            ->assertActionHidden('manageDate')
            ->assertActionHidden('reopenDate')
            ->assertActionHidden('create');
    }

    public function test_waka_kurikulum_reopens_a_past_date_and_teachers_are_told(): void
    {
        $teacher = User::factory()->create();
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();
        $waka = $this->waka();
        $this->actingAs($waka);

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('reopenDate', data: ['from' => '2026-10-23', 'to' => '2026-10-23', 'reason' => 'Server sempat bermasalah.'])
            ->assertHasNoActionErrors();

        $this->assertTrue(AttendanceReopening::isOpenFor('2026-10-23'));
        $this->assertSame(1, $teacher->notifications()->count());
        $this->assertSame(0, $principal->notifications()->count());
        $this->assertSame(0, $waka->notifications()->count());
    }

    public function test_only_effective_past_dates_within_the_school_year_can_be_reopened(): void
    {
        Semester::factory()->active()->create(['starts_on' => '2026-07-13']);

        $this->actingAs($this->waka());

        $cases = [
            'Sabtu dan Minggu saja bukan hari kerja' => ['2026-10-24', '2026-10-25', 'to'],
            'Besok belum lewat' => ['2026-10-23', '2026-10-27', 'to'],
            'Sebelum semester dimulai' => ['2026-06-01', '2026-06-05', 'from'],
            'Sampai lebih awal dari mulai' => ['2026-10-23', '2026-10-20', 'to'],
        ];

        foreach ($cases as $reason => [$from, $to, $field]) {
            Livewire::test(ManageTeacherAttendances::class)
                ->callAction('reopenDate', data: ['from' => $from, 'to' => $to, 'reason' => $reason])
                ->assertHasActionErrors([$field]);
        }

        $this->assertSame(0, AttendanceReopening::count());
    }

    public function test_a_whole_stretch_can_be_reopened_from_the_start_of_the_semester_skipping_days_off(): void
    {
        $semester = Semester::factory()->active()->create(['starts_on' => '2026-07-13']);
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-08-17', 'status' => CalendarDayStatus::Libur]);
        $teacher = User::factory()->create();

        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('reopenDate', data: ['from' => '2026-07-13', 'to' => '2026-10-23', 'reason' => 'Rapel absensi sejak awal semester.'])
            ->assertHasNoActionErrors();

        // Every Monday to Friday between the two dates, minus the one holiday.
        $expected = collect(range(0, Carbon::parse('2026-07-13')->diffInDays('2026-10-23')))
            ->map(fn (int $offset): Carbon => Carbon::parse('2026-07-13')->addDays($offset))
            ->filter(fn (Carbon $date): bool => $date->isWeekday() && $date->toDateString() !== '2026-08-17')
            ->count();

        $this->assertGreaterThan(60, $expected);
        $this->assertSame($expected, AttendanceReopening::query()->open()->count());
        $this->assertTrue(AttendanceReopening::isOpenFor('2026-07-13'));
        $this->assertTrue(AttendanceReopening::isOpenFor('2026-10-23'));
        $this->assertFalse(AttendanceReopening::isOpenFor('2026-07-18')); // a Saturday
        $this->assertFalse(AttendanceReopening::isOpenFor('2026-08-17')); // a holiday

        // One notification per teacher, however many days were opened, naming the stretch.
        $this->assertSame(1, $teacher->notifications()->count());
        $this->assertStringContainsString('13 Juli 2026 sampai 23 Oktober 2026', $teacher->notifications()->first()->data['body']);
    }

    public function test_reopening_again_does_not_duplicate_and_brings_back_a_closed_date(): void
    {
        AttendanceReopening::factory()->closed()->create(['date' => '2026-10-22']);
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('reopenDate', data: ['from' => '2026-10-21', 'to' => '2026-10-23', 'reason' => 'Dibuka lagi.'])
            ->assertHasNoActionErrors();

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('reopenDate', data: ['from' => '2026-10-21', 'to' => '2026-10-23', 'reason' => 'Dibuka lagi.'])
            ->assertHasNoActionErrors();

        $this->assertSame(3, AttendanceReopening::count());
        $this->assertSame(3, AttendanceReopening::query()->open()->count());
    }

    public function test_the_reopen_form_starts_from_the_first_day_of_the_semester(): void
    {
        Semester::factory()->create(['starts_on' => '2026-07-13']);
        Semester::factory()->create(['starts_on' => '2026-01-05']);

        $this->assertSame('2026-01-05', ManageTeacherAttendances::earliestReopenableDate()->toDateString());
    }

    public function test_a_teacher_sees_the_reopened_dates_by_month_and_moves_on_to_the_next_after_saving(): void
    {
        $teacher = User::factory()->create();

        foreach (['2026-09-29', '2026-10-21', '2026-10-22'] as $date) {
            AttendanceReopening::factory()->create(['date' => $date]);
        }

        $this->actingAs($teacher);

        $component = Livewire::test(AttendanceSelf::class);

        $html = $component->html();

        $this->assertStringContainsString('3 tanggal belum diisi', $html);
        $this->assertStringContainsString('Isi dari yang paling awal', $html);
        $this->assertStringContainsString('September 2026 · 1 tanggal', $html);
        $this->assertStringContainsString('Oktober 2026 · 2 tanggal', $html);

        $component
            ->call('selectDate', '2026-09-29')
            ->fillForm(['status' => AttendanceStatus::Hadir->value])
            ->call('save')
            ->assertSet('selectedDate', '2026-10-21');

        $component
            ->fillForm(['status' => AttendanceStatus::Izin->value])
            ->call('save')
            ->assertSet('selectedDate', '2026-10-22');

        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'date' => '2026-09-29', 'status' => 'hadir']);
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'date' => '2026-10-21', 'status' => 'izin']);
    }

    public function test_a_teacher_can_fill_a_reopened_date_but_not_a_closed_or_never_opened_one(): void
    {
        $teacher = User::factory()->create();
        AttendanceReopening::factory()->create(['date' => '2026-10-23']);
        AttendanceReopening::factory()->closed()->create(['date' => '2026-10-22']);
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->assertSee('Dibuka kembali untuk Anda')
            ->call('selectDate', '2026-10-23')
            ->assertSet('selectedDate', '2026-10-23')
            ->fillForm(['status' => AttendanceStatus::Hadir->value])
            ->call('save');

        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'date' => '2026-10-23', 'status' => 'hadir']);
        $this->assertDatabaseMissing('teacher_attendances', ['teacher_id' => $teacher->id, 'date' => '2026-10-26']);

        $component = Livewire::test(AttendanceSelf::class)->call('selectDate', '2026-10-22');
        $this->assertFalse($component->instance()->isFillingSelectedDate());
        $component->assertSee('Belum ada data absensi untuk tanggal ini.');
    }

    public function test_closing_a_reopened_date_stops_teachers_from_filling_it(): void
    {
        AttendanceReopening::factory()->create(['date' => '2026-10-23']);
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('closeReopened', data: ['dates' => ['2026-10-23']])
            ->assertHasNoActionErrors();

        $this->assertFalse(AttendanceReopening::isOpenFor('2026-10-23'));
        $this->assertNotNull(AttendanceReopening::query()->sole()->closed_at);
    }

    public function test_the_change_history_lists_this_months_entries_with_who_and_why(): void
    {
        $teacher = User::factory()->create(['name' => 'Bu Aminah']);
        $log = TeacherAttendanceLog::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => '2026-10-20',
            'before' => ['status' => 'alpa'],
            'after' => ['status' => 'izin'],
            'reason' => 'Surat dokter menyusul.',
        ]);
        CalendarDayLog::factory()->create(['date' => '2026-10-28', 'notes' => 'Cuti bersama.']);

        $html = view('filament.resources.teacher-attendances.history-all', [
            'month' => Carbon::parse('2026-10-26'),
            'attendanceLogs' => TeacherAttendanceLog::with(['teacher', 'actor'])->get(),
            'calendarLogs' => CalendarDayLog::with('actor')->get(),
        ])->render();

        $this->assertStringContainsString('Bu Aminah', $html);
        $this->assertStringContainsString('Tanpa Keterangan → Izin', preg_replace('/\s+/', ' ', strip_tags($html)));
        $this->assertStringContainsString($log->actor->name, $html);
        $this->assertStringContainsString('Surat dokter menyusul.', $html);
        $this->assertStringContainsString('Cuti bersama.', $html);
    }

    public function test_the_history_actions_are_offered_to_waka_kurikulum(): void
    {
        $this->actingAs($this->waka());

        Livewire::test(ManageTeacherAttendances::class)
            ->assertActionVisible('changeHistory')
            ->assertActionVisible('manageDate')
            ->assertActionVisible('reopenDate');
    }
}
