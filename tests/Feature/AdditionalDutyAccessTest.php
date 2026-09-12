<?php

namespace Tests\Feature;

use App\Enums\DutyType;
use App\Enums\Role;
use App\Filament\Resources\AdditionalDuties\AdditionalDutyResource;
use App\Filament\Resources\AdditionalDuties\Pages\ManageAdditionalDuties;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdditionalDutyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_additional_duty_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(AdditionalDutyResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_cannot_open_the_additional_duty_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())
            ->get(AdditionalDutyResource::getUrl())
            ->assertForbidden();
    }

    public function test_a_plain_teacher_cannot_open_the_additional_duty_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(AdditionalDutyResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_record_an_additional_duty(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageAdditionalDuties::class)
            ->callAction('create', data: [
                'teacher_id' => $teacher->id,
                'title' => 'Kepala Perpustakaan',
                'type' => DutyType::Tambahan->value,
                'jp' => 2,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('additional_duties', [
            'teacher_id' => $teacher->id,
            'title' => 'Kepala Perpustakaan',
            'jp' => 2,
        ]);
    }
}
