<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Pages\AttendanceSelf;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherAttendanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_attendance_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(TeacherAttendanceResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_attendance_but_not_create_it(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(TeacherAttendanceResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', TeacherAttendance::class));
    }

    public function test_a_plain_teacher_cannot_open_the_attendance_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(TeacherAttendanceResource::getUrl())
            ->assertForbidden();
    }

    public function test_curriculum_management_cannot_record_attendance_for_another_teacher(): void
    {
        $teacher = User::factory()->create();
        $curriculumManager = User::factory()->withRoles(Role::AdminKurikulum)->create();

        $this->assertFalse($curriculumManager->can('create', TeacherAttendance::class));
        $this->assertFalse($curriculumManager->can('update', TeacherAttendance::factory()->create(['teacher_id' => $teacher->id])));
    }

    public function test_super_admin_can_correct_attendance_for_another_teacher(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageTeacherAttendances::class)
            ->callAction('create', data: [
                'teacher_id' => $teacher->id,
                'date' => '2026-09-14',
                'status' => AttendanceStatus::Sakit->value,
                'notes' => 'Demam',
                'change_reason' => 'Guru tidak sempat mengisi, diisi sesuai surat dokter.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'status' => AttendanceStatus::Sakit->value,
        ]);
    }

    public function test_administrators_can_focus_the_attendance_table_on_a_selected_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $selectedAttendance = TeacherAttendance::factory()->create(['date' => '2026-09-10']);
        $otherAttendance = TeacherAttendance::factory()->create(['date' => '2026-09-11']);
        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageTeacherAttendances::class)
            ->assertSet('selectedDate', '2026-09-14')
            ->call('selectDate', '2026-09-10')
            ->assertSet('selectedDate', '2026-09-10')
            ->assertCanSeeTableRecords([$selectedAttendance])
            ->assertCanNotSeeTableRecords([$otherAttendance]);
    }

    public function test_any_non_principal_teacher_can_open_absensi_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(AttendanceSelf::getUrl())
            ->assertOk();
    }

    public function test_the_principal_sees_a_message_instead_of_the_form(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(AttendanceSelf::class)
            ->assertSee('Kepala Sekolah tidak perlu mengisi absensi');
    }

    public function test_a_teacher_can_fill_their_own_attendance_for_today(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->fillForm(['status' => AttendanceStatus::Hadir->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'date' => '2026-09-14',
            'status' => AttendanceStatus::Hadir->value,
        ]);
    }

    public function test_pulang_cepat_requires_a_reason_and_saves_it_for_the_report(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->fillForm(['status' => AttendanceStatus::PulangCepat->value])
            ->call('save')
            ->assertHasFormErrors(['early_leave_reason']);

        Livewire::test(AttendanceSelf::class)
            ->fillForm([
                'status' => AttendanceStatus::PulangCepat->value,
                'early_leave_reason' => 'Kontrol dokter',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'date' => '2026-09-14',
            'status' => AttendanceStatus::PulangCepat->value,
            'early_leave_reason' => 'Kontrol dokter',
        ]);
    }

    public function test_a_teacher_can_select_a_date_from_the_current_month(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->assertSet('selectedDate', '2026-09-14')
            ->call('selectDate', '2026-09-10')
            ->assertSet('selectedDate', '2026-09-10');
    }

    public function test_filling_again_the_same_day_keeps_the_original_attendance(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        Livewire::test(AttendanceSelf::class)
            ->fillForm(['status' => AttendanceStatus::Hadir->value])
            ->call('save');

        Livewire::test(AttendanceSelf::class)
            ->fillForm(['status' => AttendanceStatus::Sakit->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, TeacherAttendance::query()->where('teacher_id', $teacher->id)->count());
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'status' => AttendanceStatus::Hadir->value,
        ]);
    }

    public function test_cuti_requires_a_leave_period(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $this->actingAs(User::factory()->create());

        Livewire::test(AttendanceSelf::class)
            ->fillForm(['status' => AttendanceStatus::Cuti->value])
            ->call('save')
            ->assertHasFormErrors(['leave_starts_on', 'leave_ends_on']);
    }
}
