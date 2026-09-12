<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\Monev;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MonevAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_any_signed_in_user_can_open_monev(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(Monev::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_only_sees_their_own_row(): void
    {
        $me = User::factory()->create();
        User::factory()->count(3)->create();

        $this->actingAs($me);

        $rows = Livewire::test(Monev::class)->instance()->getTeacherRows();

        $this->assertCount(1, $rows);
        $this->assertSame($me->id, $rows->first()['teacher']->id);
    }

    public function test_leadership_sees_every_active_teacher_except_the_principal(): void
    {
        User::factory()->count(3)->create();
        User::factory()->withRoles(Role::KepalaSekolah)->create();
        User::factory()->inactive()->create();

        $admin = User::factory()->withRoles(Role::AdminKurikulum)->create();
        $this->actingAs($admin);

        $rows = Livewire::test(Monev::class)->instance()->getTeacherRows();

        // 3 plain teachers + the admin themselves, minus the principal and the inactive account.
        $this->assertCount(4, $rows);
        $this->assertTrue($rows->pluck('teacher.id')->contains($admin->id));
    }

    public function test_status_is_merah_when_no_assessment_exists_this_month(): void
    {
        $saturday = $this->nextSaturday();
        Carbon::setTestNow($saturday);

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        $rows = Livewire::test(Monev::class)->instance()->getTeacherRows();

        $this->assertSame('merah', $rows->first()['status']);
    }

    public function test_status_is_hijau_when_fully_compliant(): void
    {
        $saturday = $this->nextSaturday();
        Carbon::setTestNow($saturday);

        $futureMonth = $saturday->copy()->addMonth()->format('Y-m');

        $teacher = User::factory()->create();
        Assessment::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => "{$futureMonth}-15",
        ]);

        $this->actingAs($teacher);

        $component = Livewire::test(Monev::class)->set('month', $futureMonth);
        $rows = $component->instance()->getTeacherRows();

        $this->assertSame('hijau', $rows->first()['status']);
        $this->assertSame(100, $rows->first()['attendance_percentage']);
    }

    public function test_status_is_kuning_when_attendance_is_below_80_percent(): void
    {
        $saturday = $this->nextSaturday()->addWeek();
        Carbon::setTestNow($saturday);

        $teacher = User::factory()->create();
        Assessment::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => $saturday->toDateString(),
        ]);

        $this->actingAs($teacher);

        $rows = Livewire::test(Monev::class)->instance()->getTeacherRows();

        $this->assertSame('kuning', $rows->first()['status']);
        $this->assertLessThan(80, $rows->first()['attendance_percentage']);
    }

    /**
     * A Saturday is never a school day, so it never requires a journal — freezing "today"
     * to one lets a test control attendance/assessment scenarios without the journal
     * "hari ini" check interfering.
     */
    private function nextSaturday(): Carbon
    {
        $date = Carbon::now()->startOfMonth();

        return $date->isSaturday() ? $date : $date->next(Carbon::SATURDAY);
    }
}
