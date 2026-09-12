<?php

namespace Tests\Feature;

use App\Enums\ExtracurricularAttendanceStatus;
use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Resources\ExtracurricularAttendances\ExtracurricularAttendanceResource;
use App\Filament\Resources\ExtracurricularAttendances\Pages\ManageExtracurricularAttendances;
use App\Filament\Resources\Extracurriculars\ExtracurricularResource;
use App\Filament\Resources\Extracurriculars\Pages\ManageExtracurriculars;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ExtracurricularAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_user_can_open_the_schedule_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ExtracurricularResource::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_cannot_create_a_schedule_entry(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', Extracurricular::class));
    }

    public function test_the_ekskul_coordinator_can_create_a_schedule_entry_with_assistant_teachers(): void
    {
        $teachers = User::factory()->count(2)->create();

        $this->actingAs(User::factory()->withRoles(Role::KoordinatorEkskul)->create());

        Livewire::test(ManageExtracurriculars::class)
            ->callAction('create', data: [
                'name' => 'Pramuka',
                'day' => SchoolDay::Selasa->value,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'location' => 'Lapangan',
                'assistantTeachers' => $teachers->pluck('id')->all(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('extracurriculars', ['name' => 'Pramuka']);
        $this->assertDatabaseCount('extracurricular_teacher', 2);
    }

    public function test_any_signed_in_user_can_open_the_attendance_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ExtracurricularAttendanceResource::getUrl())
            ->assertOk();
    }

    public function test_a_teacher_can_log_attendance_for_an_extracurricular_they_accompany_that_day(): void
    {
        $teacher = User::factory()->create();
        $tuesday = Carbon::parse('next tuesday');

        $extracurricular = Extracurricular::factory()->create(['day' => SchoolDay::Selasa]);
        $extracurricular->assistantTeachers()->attach($teacher);

        $this->actingAs($teacher);

        Livewire::test(ManageExtracurricularAttendances::class)
            ->callAction('create', data: [
                'date' => $tuesday->toDateString(),
                'extracurricular_id' => $extracurricular->id,
                'status' => ExtracurricularAttendanceStatus::Hadir->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('extracurricular_attendances', [
            'extracurricular_id' => $extracurricular->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_the_day_filtered_options_exclude_extracurriculars_the_teacher_does_not_accompany(): void
    {
        $teacher = User::factory()->create();
        $tuesday = Carbon::parse('next tuesday');

        Extracurricular::factory()->create(['day' => SchoolDay::Selasa, 'name' => 'Bukan Punya Saya']);

        $this->actingAs($teacher);

        $this->assertSame([], ExtracurricularAttendanceResource::extracurricularOptions($tuesday->toDateString()));
    }

    public function test_a_teacher_only_sees_their_own_attendance_records(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        ExtracurricularAttendance::factory()->create(['teacher_id' => $me->id]);
        ExtracurricularAttendance::factory()->create(['teacher_id' => $someoneElse->id]);

        $this->actingAs($me);

        $this->assertSame(1, ExtracurricularAttendanceResource::getEloquentQuery()->count());
    }

    public function test_the_ekskul_coordinator_sees_everyones_attendance_records(): void
    {
        ExtracurricularAttendance::factory()->count(2)->create();

        $this->actingAs(User::factory()->withRoles(Role::KoordinatorEkskul)->create());

        $this->assertSame(2, ExtracurricularAttendanceResource::getEloquentQuery()->count());
    }
}
