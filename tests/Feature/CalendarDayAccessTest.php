<?php

namespace Tests\Feature;

use App\Enums\CalendarDayStatus;
use App\Enums\Role;
use App\Filament\Resources\CalendarDays\CalendarDayResource;
use App\Filament\Resources\CalendarDays\Pages\ManageCalendarDays;
use App\Models\CalendarDay;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarDayAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_calendar(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(CalendarDayResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_the_calendar_but_not_mark_dates(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(CalendarDayResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', CalendarDay::class));
    }

    public function test_teachers_without_an_administrative_role_cannot_open_the_calendar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(CalendarDayResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_mark_a_date_as_a_holiday(): void
    {
        $semester = Semester::factory()->active()->create();
        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageCalendarDays::class)
            ->callAction('create', data: [
                'date' => '2026-12-25',
                'status' => CalendarDayStatus::Libur->value,
                'semester_id' => $semester->id,
                'notes' => 'Hari Natal',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(CalendarDayStatus::Libur, CalendarDay::statusFor('2026-12-25'));
    }

    public function test_a_date_cannot_be_marked_twice(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->for($semester)->create(['date' => '2026-12-25']);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageCalendarDays::class)
            ->callAction('create', data: [
                'date' => '2026-12-25',
                'status' => CalendarDayStatus::Kegiatan->value,
                'semester_id' => $semester->id,
            ])
            ->assertHasActionErrors(['date']);
    }
}
