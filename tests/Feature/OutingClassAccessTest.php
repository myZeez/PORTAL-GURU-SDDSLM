<?php

namespace Tests\Feature;

use App\Enums\OutingStatus;
use App\Enums\Role;
use App\Filament\Resources\OutingClasses\OutingClassResource;
use App\Filament\Resources\OutingClasses\Pages\ManageOutingClasses;
use App\Models\Classroom;
use App\Models\OutingClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OutingClassAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_non_principal_user_can_open_the_outing_resource(): void
    {
        foreach ([User::factory()->create(), User::factory()->withRoles(Role::WakaKurikulum)->create()] as $user) {
            $this->actingAs($user)
                ->get(OutingClassResource::getUrl())
                ->assertOk();
        }
    }

    public function test_the_principal_cannot_submit_a_request(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(OutingClassResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', OutingClass::class));
    }

    public function test_a_teacher_can_submit_their_own_outing_request(): void
    {
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(ManageOutingClasses::class)
            ->callAction('create', data: [
                'classroom_id' => $classroom->id,
                'date' => '2026-09-25',
                'destination' => 'Museum Kota',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('outing_classes', [
            'classroom_id' => $classroom->id,
            'requested_by' => $teacher->id,
            'status' => OutingStatus::Menunggu->value,
        ]);
    }

    public function test_a_teacher_only_sees_their_own_requests(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        OutingClass::factory()->create(['requested_by' => $me->id, 'destination' => 'Tujuan Saya']);
        OutingClass::factory()->create(['requested_by' => $someoneElse->id, 'destination' => 'Tujuan Lain']);

        $this->actingAs($me)
            ->get(OutingClassResource::getUrl())
            ->assertOk()
            ->assertSee('Tujuan Saya')
            ->assertDontSee('Tujuan Lain');
    }

    public function test_an_administrator_sees_every_request_and_can_approve(): void
    {
        $outing = OutingClass::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageOutingClasses::class)
            ->callTableAction('approve', $outing);

        $this->assertSame(OutingStatus::Disetujui, $outing->refresh()->status);
        $this->assertSame(1, $outing->requestedBy->fresh()->notifications()->count());
    }

    public function test_a_teacher_cannot_approve_their_own_request(): void
    {
        $teacher = User::factory()->create();
        $outing = OutingClass::factory()->create(['requested_by' => $teacher->id]);

        $this->assertFalse($teacher->can('update', $outing));
    }
}
