<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\PerformanceSnapshot;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\User;
use Database\Seeders\RevisionDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RevisionDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_fills_simpati_history_and_findings_and_can_be_run_again_without_duplicates(): void
    {
        Semester::factory()->active()->create();
        User::factory()->withRoles(Role::WakaKurikulum)->create();
        User::factory()->count(4)->create();
        Classroom::factory()->count(3)->create()->each(fn (Classroom $classroom) => $classroom->update(['assistant_teacher_id' => User::factory()->create()->id]));

        $this->seed(RevisionDemoSeeder::class);

        $checks = SimpatiCheck::count();
        $snapshots = PerformanceSnapshot::count();

        $this->assertGreaterThan(0, $checks);
        $this->assertGreaterThan(0, $snapshots);
        $this->assertSame(3, PerformanceFollowUp::count());
        $this->assertFalse(PerformanceSetting::current()->canScore());

        $this->seed(RevisionDemoSeeder::class);

        $this->assertSame($checks, SimpatiCheck::count());
        $this->assertSame($snapshots, PerformanceSnapshot::count());
        $this->assertSame(3, PerformanceFollowUp::count());
    }
}
