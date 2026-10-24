<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\Extracurricular;
use App\Models\OutingClass;
use App\Models\PidReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_waka_sarpras_can_manage_pid_but_cannot_open_curriculum_master_data(): void
    {
        $wakaSarpras = User::factory()->withRoles(Role::WakaSarpras)->create();
        $reservation = PidReservation::factory()->create();

        $this->assertTrue($wakaSarpras->can('update', $reservation));
        $this->assertFalse($wakaSarpras->can('create', Extracurricular::class));

        $this->actingAs($wakaSarpras)
            ->get(SubjectResource::getUrl())
            ->assertForbidden();
    }

    public function test_curriculum_management_reviews_outings_but_not_other_peoples_pid_reservations(): void
    {
        $curriculumManager = User::factory()->withRoles(Role::AdminKurikulum)->create();
        $outing = OutingClass::factory()->create();
        $reservation = PidReservation::factory()->create();

        $this->assertTrue($curriculumManager->can('view', $outing));
        $this->assertFalse($curriculumManager->can('update', $outing));
        $this->assertFalse($curriculumManager->can('view', $reservation));
    }

    public function test_ekskul_coordinator_manages_extracurriculars_without_curriculum_or_facilities_access(): void
    {
        $coordinator = User::factory()->withRoles(Role::KoordinatorEkskul)->create();
        $reservation = PidReservation::factory()->create();

        $this->assertTrue($coordinator->can('create', Extracurricular::class));
        $this->assertFalse($coordinator->can('update', $reservation));
        $this->assertFalse($coordinator->isCurriculumManager());
        $this->assertFalse($coordinator->isFacilitiesManager());
    }
}
