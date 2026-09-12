<?php

namespace Tests\Feature;

use App\Enums\OutingStatus;
use App\Enums\Role;
use App\Filament\Resources\PidReservations\Pages\ManagePidReservations;
use App\Filament\Resources\PidReservations\PidReservationResource;
use App\Models\PidReservation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PidReservationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_non_principal_user_can_open_the_pid_resource(): void
    {
        foreach ([User::factory()->create(), User::factory()->withRoles(Role::WakaKurikulum)->create()] as $user) {
            $this->actingAs($user)
                ->get(PidReservationResource::getUrl())
                ->assertOk();
        }
    }

    public function test_the_principal_cannot_reserve_the_pid(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->assertFalse($principal->can('create', PidReservation::class));
    }

    public function test_a_teacher_can_reserve_the_pid(): void
    {
        $teacher = User::factory()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(ManagePidReservations::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'location' => 'Ruang PID 1',
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'subject_id' => $subject->id,
                'purpose' => 'Menonton video pembelajaran',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('pid_reservations', [
            'location' => 'Ruang PID 1',
            'requested_by' => $teacher->id,
            'status' => OutingStatus::Menunggu->value,
        ]);
    }

    public function test_an_overlapping_reservation_for_the_same_place_is_rejected(): void
    {
        PidReservation::factory()->create([
            'date' => '2026-09-20',
            'location' => 'Ruang PID 1',
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ManagePidReservations::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'location' => 'Ruang PID 1',
                'starts_at' => '08:30',
                'ends_at' => '09:30',
                'subject_id' => Subject::factory()->create()->id,
                'purpose' => 'Tumpang tindih',
            ])
            ->assertHasActionErrors(['ends_at']);
    }

    public function test_a_non_overlapping_reservation_for_the_same_place_succeeds(): void
    {
        PidReservation::factory()->create([
            'date' => '2026-09-20',
            'location' => 'Ruang PID 1',
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ManagePidReservations::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'location' => 'Ruang PID 1',
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'subject_id' => Subject::factory()->create()->id,
                'purpose' => 'Tidak tumpang tindih',
            ])
            ->assertHasNoActionErrors();
    }

    public function test_a_different_location_at_the_same_time_does_not_conflict(): void
    {
        PidReservation::factory()->create([
            'date' => '2026-09-20',
            'location' => 'Ruang PID 1',
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ManagePidReservations::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'location' => 'Ruang PID 2',
                'starts_at' => '08:00',
                'ends_at' => '09:00',
                'subject_id' => Subject::factory()->create()->id,
                'purpose' => 'Tempat berbeda',
            ])
            ->assertHasNoActionErrors();
    }

    public function test_an_administrator_can_approve_a_reservation(): void
    {
        $reservation = PidReservation::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManagePidReservations::class)
            ->callTableAction('approve', $reservation);

        $this->assertSame(OutingStatus::Disetujui, $reservation->refresh()->status);
        $this->assertSame(1, $reservation->requestedBy->fresh()->notifications()->count());
    }

    public function test_a_teacher_only_sees_their_own_reservations(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        PidReservation::factory()->create(['requested_by' => $me->id, 'purpose' => 'Keperluan Saya']);
        PidReservation::factory()->create(['requested_by' => $someoneElse->id, 'purpose' => 'Keperluan Lain']);

        $this->actingAs($me)
            ->get(PidReservationResource::getUrl())
            ->assertOk()
            ->assertSee('Keperluan Saya')
            ->assertDontSee('Keperluan Lain');
    }
}
