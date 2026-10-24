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
use Illuminate\Support\Carbon;
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

    public function test_only_waka_kurikulum_can_approve_a_request(): void
    {
        $outing = OutingClass::factory()->create();
        $approver = User::factory()->withRoles(Role::WakaKurikulum)->create();

        $this->actingAs($approver);

        Livewire::test(ManageOutingClasses::class)
            ->callTableAction('approve', $outing);

        $this->assertSame(OutingStatus::Disetujui, $outing->refresh()->status);
        $this->assertSame(1, $outing->requestedBy->fresh()->notifications()->count());
    }

    public function test_an_approval_records_who_approved_and_when(): void
    {
        Carbon::setTestNow('2026-10-26 09:30:00');

        $outing = OutingClass::factory()->create();
        $approver = User::factory()->withRoles(Role::WakaKurikulum)->create();

        $this->actingAs($approver);

        Livewire::test(ManageOutingClasses::class)
            ->callTableAction('approve', $outing);

        $outing->refresh();

        $this->assertSame($approver->id, $outing->approved_by);
        $this->assertTrue($outing->approved_at->equalTo('2026-10-26 09:30:00'));

        Carbon::setTestNow();
    }

    public function test_a_pending_request_has_no_approver_and_an_approved_one_cannot_be_approved_again(): void
    {
        $pending = OutingClass::factory()->create();
        $approved = OutingClass::factory()->create();
        $first = User::factory()->withRoles(Role::WakaKurikulum)->create();
        $approved->approve($first);

        $this->assertNull($pending->approved_by);
        $this->assertNull($pending->approved_at);

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageOutingClasses::class)
            ->assertTableActionHidden('approve', $approved)
            ->assertTableActionVisible('approve', $pending);

        $this->assertSame($first->id, $approved->refresh()->approved_by);
    }

    public function test_the_approver_cannot_be_set_through_the_request_form(): void
    {
        $teacher = User::factory()->create();
        $impostor = User::factory()->withRoles(Role::WakaKurikulum)->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(ManageOutingClasses::class)
            ->callAction('create', data: [
                'classroom_id' => $classroom->id,
                'date' => '2026-09-25',
                'destination' => 'Museum Kota',
                'approved_by' => $impostor->id,
                'status' => OutingStatus::Disetujui->value,
            ]);

        $created = OutingClass::query()->where('destination', 'Museum Kota')->firstOrFail();

        $this->assertNull($created->approved_by);
        $this->assertSame(OutingStatus::Menunggu, $created->status);
    }

    public function test_other_administrators_cannot_approve_a_request(): void
    {
        $outing = OutingClass::factory()->create();

        $this->assertFalse(User::factory()->withRoles(Role::AdminKurikulum)->create()->can('update', $outing));
    }

    public function test_a_teacher_cannot_approve_their_own_request(): void
    {
        $teacher = User::factory()->create();
        $outing = OutingClass::factory()->create(['requested_by' => $teacher->id]);

        $this->assertFalse($teacher->can('update', $outing));
    }
}
