<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\MySupervisions;
use App\Filament\Resources\Supervisions\Pages\ManageSupervisions;
use App\Filament\Resources\Supervisions\SupervisionResource;
use App\Models\Subject;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_supervision_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(SupervisionResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_but_not_schedule_supervisions(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(SupervisionResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', Supervision::class));
    }

    public function test_a_plain_teacher_cannot_open_the_supervision_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(SupervisionResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_schedule_a_supervision(): void
    {
        $teacher = User::factory()->create();
        $supervisor = User::factory()->create();
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageSupervisions::class)
            ->callAction('create', data: [
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'supervisor_id' => $supervisor->id,
                'date' => '2026-09-20',
                'location' => 'Kelas I-A',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('supervisions', [
            'teacher_id' => $teacher->id,
            'supervisor_id' => $supervisor->id,
        ]);
    }

    public function test_any_signed_in_user_can_open_supervisi_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MySupervisions::getUrl())
            ->assertOk();
    }

    public function test_a_teacher_only_sees_their_own_supervisions(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        Supervision::factory()->create(['teacher_id' => $me->id, 'location' => 'Ruang Saya']);
        Supervision::factory()->create(['teacher_id' => $someoneElse->id, 'location' => 'Ruang Lain']);

        $this->actingAs($me)
            ->get(MySupervisions::getUrl())
            ->assertOk()
            ->assertSee('Ruang Saya')
            ->assertDontSee('Ruang Lain');
    }
}
