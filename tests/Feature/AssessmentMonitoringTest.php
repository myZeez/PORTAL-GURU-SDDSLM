<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\Menu;
use App\Filament\Resources\AssessmentMonitoring\AssessmentMonitoringResource;
use App\Filament\Resources\AssessmentMonitoring\Pages\ManageAssessmentMonitoring;
use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\RecordAudit;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function kiki(): User
    {
        return User::factory()->withRoles(Role::WakaKurikulum)->create(['name' => 'Kiki Fauziah']);
    }

    private function febby(): User
    {
        return User::factory()->withRoles(Role::AdminKurikulum)->create(['name' => 'Febby']);
    }

    public function test_the_monitoring_menu_is_only_for_kiki_febby_and_super_admin(): void
    {
        foreach ([$this->kiki(), $this->febby(), User::factory()->withRoles(Role::SuperAdmin)->create()] as $user) {
            $this->actingAs($user);

            $this->get(AssessmentMonitoringResource::getUrl())->assertOk();
            $this->assertTrue(AssessmentMonitoringResource::canAccess());
        }

        foreach ([null, Role::KepalaSekolah, Role::WakaSarpras, Role::KoordinatorEkskul] as $role) {
            $this->actingAs($role ? User::factory()->withRoles($role)->create() : User::factory()->create());

            $this->get(AssessmentMonitoringResource::getUrl())->assertForbidden();
            $this->assertFalse(AssessmentMonitoringResource::canAccess());
        }
    }

    public function test_it_sits_under_monitoring_in_the_mobile_menu_for_those_who_may_open_it(): void
    {
        $labelsInMonitoring = function (User $user): array {
            $this->actingAs($user);

            return collect(Livewire::test(Menu::class)->instance()->getModuleGroups())
                ->firstWhere('group', 'Monitoring')['modules'] ?? [];
        };

        $this->assertContains('Penilaian Sumatif', collect($labelsInMonitoring($this->febby()))->pluck('label')->all());
        $this->assertNotContains('Penilaian Sumatif', collect($labelsInMonitoring(User::factory()->create()))->pluck('label')->all());
        $this->assertSame('Monitoring', AssessmentMonitoringResource::getNavigationGroup());
        $this->assertSame('Penilaian Sumatif', AssessmentMonitoringResource::getNavigationLabel());
    }

    public function test_it_lists_every_teachers_assessments_with_who_entered_them(): void
    {
        $first = Assessment::factory()->create(['teacher_id' => User::factory()->create(['name' => 'Bu Satu'])->id, 'chapter' => 'Bab Satu']);
        $second = Assessment::factory()->create(['teacher_id' => User::factory()->create(['name' => 'Pak Dua'])->id, 'chapter' => 'Bab Dua']);

        $this->actingAs($this->febby());

        Livewire::test(ManageAssessmentMonitoring::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertSee('Bu Satu')
            ->assertSee('Pak Dua');
    }

    public function test_the_summary_says_which_classes_and_teachers_have_not_done_it_yet(): void
    {
        $semester = Semester::factory()->active()->create(['starts_on' => '2026-07-13']);
        $classrooms = Classroom::factory()->count(3)->create();
        $doing = User::factory()->create(['name' => 'Guru Rajin']);
        $notYet = User::factory()->create(['name' => 'Guru Belum']);
        $notTeaching = User::factory()->create(['name' => 'Bukan Pengajar']);

        foreach ([$doing, $notYet] as $teacher) {
            Schedule::factory()->create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'classroom_id' => $classrooms->first()->id]);
        }

        Assessment::factory()->create(['teacher_id' => $doing->id, 'classroom_id' => $classrooms[0]->id, 'date' => '2026-10-20', 'is_completed' => true]);
        // Before the semester began, so it does not count towards it.
        Assessment::factory()->create(['teacher_id' => $notYet->id, 'classroom_id' => $classrooms[1]->id, 'date' => '2026-06-01']);

        $this->actingAs($this->kiki());

        $coverage = Livewire::test(ManageAssessmentMonitoring::class)->instance()->getCoverage();

        $this->assertSame(['total' => 3, 'done' => 1], collect($coverage['classrooms'])->only(['total', 'done'])->all());
        $this->assertEqualsCanonicalizing([$classrooms[1]->id, $classrooms[2]->id], $coverage['classrooms']['missing']->pluck('id')->all());
        $this->assertSame(2, $coverage['teachers']['total']);
        $this->assertSame(1, $coverage['teachers']['done']);
        $this->assertSame(['Guru Belum'], $coverage['teachers']['missing']->pluck('name')->all());
        $this->assertSame(1, $coverage['assessments']);
        $this->assertSame(1, $coverage['completed']);

        $html = Livewire::test(ManageAssessmentMonitoring::class)->html();

        $this->assertStringContainsString('Rombel yang belum melaksanakan', $html);
        $this->assertStringContainsString('Guru Belum', $html);
        $this->assertStringNotContainsString('Bukan Pengajar', $html);
    }

    public function test_kiki_can_change_a_teachers_entry_and_the_change_is_traced(): void
    {
        $teacher = User::factory()->create(['name' => 'Bu Salah Input']);
        $assessment = Assessment::factory()->create(['teacher_id' => $teacher->id, 'chapter' => 'Bab Salah', 'is_completed' => false]);
        $kiki = $this->kiki();

        $this->actingAs($kiki);

        Livewire::test(ManageAssessmentMonitoring::class)
            ->assertTableActionVisible('edit', $assessment)
            ->callTableAction('edit', $assessment, data: [
                'classroom_id' => $assessment->classroom_id,
                'subject_id' => $assessment->subject_id,
                'date' => $assessment->date->toDateString(),
                'chapter' => 'Bab Benar',
                'is_completed' => true,
            ])
            ->assertHasNoTableActionErrors();

        $assessment->refresh();

        $this->assertSame('Bab Benar', $assessment->chapter);
        $this->assertTrue($assessment->is_completed);
        $this->assertSame($teacher->id, $assessment->teacher_id, 'The owner stays the teacher who entered it.');

        $audit = RecordAudit::query()->sole();

        $this->assertSame($kiki->id, $audit->actor_id);
        $this->assertSame(['Bab Salah', 'Bab Benar'], $audit->changes()['Bab']);
        $this->assertSame(['Belum', 'Ya'], $audit->changes()['Selesai']);
    }

    public function test_kiki_can_delete_a_wrong_entry_after_giving_a_reason(): void
    {
        $assessment = Assessment::factory()->create();

        $this->actingAs($this->kiki());

        Livewire::test(ManageAssessmentMonitoring::class)
            ->callTableAction('delete', $assessment, data: ['reason' => ''])
            ->assertHasTableActionErrors(['reason']);

        $this->assertModelExists($assessment);

        Livewire::test(ManageAssessmentMonitoring::class)
            ->callTableAction('delete', $assessment, data: ['reason' => 'Salah kelas'])
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($assessment);
        $this->assertSame('Salah kelas', RecordAudit::query()->sole()->reason);
    }

    public function test_febby_monitors_but_cannot_change_or_remove_anything(): void
    {
        $assessment = Assessment::factory()->create();

        $this->actingAs($this->febby());

        Livewire::test(ManageAssessmentMonitoring::class)
            ->assertCanSeeTableRecords([$assessment])
            ->assertTableActionHidden('edit', $assessment)
            ->assertTableActionHidden('delete', $assessment)
            ->assertTableBulkActionHidden('bulkDelete');

        $this->assertFalse($this->febby()->can('update', $assessment));
    }

    public function test_nobody_enters_an_assessment_from_the_monitoring_page(): void
    {
        $this->actingAs($this->kiki());

        $this->assertFalse(AssessmentMonitoringResource::canCreate());

        Livewire::test(ManageAssessmentMonitoring::class)->assertActionDoesNotExist('create');
    }

    public function test_kiki_can_clear_demo_assessments_in_one_go(): void
    {
        $assessments = Assessment::factory()->count(6)->create();

        $this->actingAs($this->kiki());

        Livewire::test(ManageAssessmentMonitoring::class)
            ->callTableBulkAction('bulkDelete', $assessments, data: ['reason' => 'Membersihkan data demo'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(0, Assessment::query()->count());
        $this->assertSame('6', RecordAudit::query()->sole()->before['Jumlah data']);
    }

    public function test_a_teacher_still_only_sees_and_enters_their_own_in_their_own_menu(): void
    {
        $me = User::factory()->create();
        $mine = Assessment::factory()->create(['teacher_id' => $me->id]);
        $others = Assessment::factory()->create();

        $this->actingAs($me);

        Livewire::test(ManageAssessments::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$others]);
    }
}
