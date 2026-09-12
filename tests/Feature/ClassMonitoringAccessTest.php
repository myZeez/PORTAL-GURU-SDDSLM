<?php

namespace Tests\Feature;

use App\Enums\ClassMonitoringItemStatus;
use App\Enums\ClassMonitoringResultStatus;
use App\Enums\Role;
use App\Filament\Pages\ConductMonitoring;
use App\Filament\Resources\MonitoringSchedules\MonitoringScheduleResource;
use App\Filament\Resources\MonitoringSchedules\Pages\ManageMonitoringSchedules;
use App\Models\Classroom;
use App\Models\MonitoringItem;
use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClassMonitoringAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_and_the_principal_can_open_the_schedule_resource(): void
    {
        foreach ([Role::WakaKurikulum, Role::AdminKurikulum] as $role) {
            $this->actingAs(User::factory()->withRoles($role)->create())
                ->get(MonitoringScheduleResource::getUrl())
                ->assertOk();
        }

        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())
            ->get(MonitoringScheduleResource::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_cannot_open_the_schedule_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MonitoringScheduleResource::getUrl())
            ->assertForbidden();
    }

    public function test_only_waka_kurikulum_can_schedule_a_monitoring_visit(): void
    {
        $this->assertTrue(User::factory()->withRoles(Role::WakaKurikulum)->create()->can('create', MonitoringSchedule::class));
        $this->assertFalse(User::factory()->withRoles(Role::AdminKurikulum)->create()->can('create', MonitoringSchedule::class));
    }

    public function test_scheduling_a_visit_notifies_the_homeroom_and_assistant_teacher(): void
    {
        $wali = User::factory()->create();
        $pendamping = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'homeroom_teacher_id' => $wali->id,
            'assistant_teacher_id' => $pendamping->id,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageMonitoringSchedules::class)
            ->callAction('create', data: [
                'date' => '2026-09-20',
                'classroom_id' => $classroom->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $wali->fresh()->notifications()->count());
        $this->assertSame(1, $pendamping->fresh()->notifications()->count());
    }

    public function test_admin_kurikulum_can_conduct_a_scheduled_visit(): void
    {
        $schedule = MonitoringSchedule::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ConductMonitoring::class, ['record' => $schedule->id])
            ->assertOk();

        $this->assertDatabaseHas('monitoring_results', ['monitoring_schedule_id' => $schedule->id]);
        $this->assertDatabaseCount('monitoring_items', 28);
    }

    public function test_a_plain_teacher_cannot_conduct_a_monitoring_visit(): void
    {
        $schedule = MonitoringSchedule::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->get(ConductMonitoring::getUrl(['record' => $schedule->id]))
            ->assertForbidden();
    }

    public function test_the_score_is_computed_from_the_checklist_items(): void
    {
        $result = MonitoringResult::factory()->create();

        MonitoringItem::factory()->count(9)->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::Lengkap,
        ]);
        MonitoringItem::factory()->count(1)->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::PerluDiperbaiki,
        ]);

        $result->computeScore();
        $result->refresh();

        $this->assertSame(90, $result->score);
        $this->assertSame(ClassMonitoringResultStatus::SangatLengkap, $result->status);
    }

    public function test_any_item_marked_belum_ada_forces_perlu_tindak_lanjut_regardless_of_score(): void
    {
        $result = MonitoringResult::factory()->create();

        MonitoringItem::factory()->count(9)->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::Lengkap,
        ]);
        MonitoringItem::factory()->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::BelumAda,
        ]);

        $result->computeScore();
        $result->refresh();

        $this->assertSame(ClassMonitoringResultStatus::PerluTindakLanjut, $result->status);
    }

    public function test_tidak_relevan_items_are_excluded_from_the_score(): void
    {
        $result = MonitoringResult::factory()->create();

        MonitoringItem::factory()->count(8)->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::Lengkap,
        ]);
        MonitoringItem::factory()->count(5)->create([
            'monitoring_result_id' => $result->id,
            'status' => ClassMonitoringItemStatus::TidakRelevan,
        ]);

        $result->computeScore();
        $result->refresh();

        $this->assertSame(100, $result->score);
        $this->assertSame(ClassMonitoringResultStatus::SangatLengkap, $result->status);
    }
}
