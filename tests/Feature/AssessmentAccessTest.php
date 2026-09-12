<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_non_principal_user_can_open_the_assessment_resource(): void
    {
        foreach ([User::factory()->create(), User::factory()->withRoles(Role::WakaKurikulum)->create()] as $user) {
            $this->actingAs($user)
                ->get(AssessmentResource::getUrl())
                ->assertOk();
        }
    }

    public function test_the_principal_can_open_it_read_only(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(AssessmentResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', Assessment::class));
    }

    public function test_a_teacher_can_record_their_own_assessment(): void
    {
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($teacher);

        Livewire::test(ManageAssessments::class)
            ->callAction('create', data: [
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'date' => '2026-09-14',
                'chapter' => 'Bab 3',
                'is_completed' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('assessments', [
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'chapter' => 'Bab 3',
            'is_completed' => true,
        ]);
    }

    public function test_an_administrator_only_manages_their_own_assessments_not_kelola(): void
    {
        $admin = User::factory()->withRoles(Role::AdminKurikulum)->create();
        $otherTeacherAssessment = Assessment::factory()->create();

        $this->actingAs($admin);

        $this->assertFalse($admin->can('update', $otherTeacherAssessment));
        $this->assertFalse($admin->can('delete', $otherTeacherAssessment));
    }

    public function test_a_teacher_only_sees_their_own_assessments_in_the_list(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        Assessment::factory()->create(['teacher_id' => $me->id, 'chapter' => 'Bab Milik Saya']);
        Assessment::factory()->create(['teacher_id' => $someoneElse->id, 'chapter' => 'Bab Milik Lain']);

        $this->actingAs($me)
            ->get(AssessmentResource::getUrl())
            ->assertOk()
            ->assertSee('Bab Milik Saya')
            ->assertDontSee('Bab Milik Lain');
    }

    public function test_the_principal_sees_everyones_assessments(): void
    {
        Assessment::factory()->create(['chapter' => 'Bab Guru A']);
        Assessment::factory()->create(['chapter' => 'Bab Guru B']);

        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())
            ->get(AssessmentResource::getUrl())
            ->assertOk()
            ->assertSee('Bab Guru A')
            ->assertSee('Bab Guru B');
    }

    public function test_a_teacher_cannot_edit_someone_elses_assessment_by_tampering_with_the_url(): void
    {
        $owner = User::factory()->create();
        $assessment = Assessment::factory()->create(['teacher_id' => $owner->id]);

        $intruder = User::factory()->create();

        $this->assertFalse($intruder->can('update', $assessment));
        $this->assertFalse($intruder->can('delete', $assessment));
    }
}
