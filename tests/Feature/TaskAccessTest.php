<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\MyTasks;
use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TaskAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_task_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(TaskResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_cannot_open_the_task_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())
            ->get(TaskResource::getUrl())
            ->assertForbidden();
    }

    public function test_a_plain_teacher_cannot_open_the_task_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(TaskResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_create_a_task_with_multiple_assignees(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageTasks::class)
            ->callAction('create', data: [
                'title' => 'Kumpulkan RPP semester ganjil',
                'assignees' => [$teacherA->id, $teacherB->id],
                'due_on' => '2026-09-30',
            ])
            ->assertHasNoActionErrors();

        $task = Task::query()->where('title', 'Kumpulkan RPP semester ganjil')->firstOrFail();
        $this->assertSame(2, $task->assignees()->count());
        $this->assertTrue($task->assignees->contains($teacherA));
        $this->assertTrue($task->assignees->contains($teacherB));
    }

    public function test_any_signed_in_user_can_open_tugas_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(MyTasks::getUrl())
            ->assertOk();

        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create())
            ->get(MyTasks::getUrl())
            ->assertOk();
    }

    public function test_a_user_only_sees_their_own_assignments(): void
    {
        $me = User::factory()->create();
        $someoneElse = User::factory()->create();

        $myTask = Task::factory()->create(['title' => 'Tugas saya']);
        $myTask->assignees()->attach($me->id);

        $otherTask = Task::factory()->create(['title' => 'Tugas orang lain']);
        $otherTask->assignees()->attach($someoneElse->id);

        $this->actingAs($me)
            ->get(MyTasks::getUrl())
            ->assertOk()
            ->assertSee('Tugas saya')
            ->assertDontSee('Tugas orang lain');
    }

    public function test_a_user_can_toggle_their_own_task_as_completed(): void
    {
        $me = User::factory()->create();
        $task = Task::factory()->create();
        $task->assignees()->attach($me->id);
        $assigneeId = $task->taskAssignees()->first()->id;

        $this->actingAs($me);

        Livewire::test(MyTasks::class)
            ->call('toggle', $assigneeId);

        $this->assertDatabaseHas('task_assignees', [
            'id' => $assigneeId,
            'is_completed' => true,
        ]);

        Livewire::test(MyTasks::class)
            ->call('toggle', $assigneeId);

        $this->assertDatabaseHas('task_assignees', [
            'id' => $assigneeId,
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    public function test_a_user_cannot_toggle_someone_elses_task(): void
    {
        $owner = User::factory()->create();
        $task = Task::factory()->create();
        $task->assignees()->attach($owner->id);
        $assigneeId = $task->taskAssignees()->first()->id;

        $this->actingAs(User::factory()->create());

        Livewire::test(MyTasks::class)
            ->call('toggle', $assigneeId);

        $this->assertDatabaseHas('task_assignees', [
            'id' => $assigneeId,
            'is_completed' => false,
        ]);
    }

    public function test_completed_tasks_sort_after_pending_ones(): void
    {
        $me = User::factory()->create();

        $soon = Task::factory()->create(['title' => 'Segera', 'due_on' => Carbon::parse('2026-09-14')]);
        $soon->assignees()->attach($me->id, ['is_completed' => true]);

        $later = Task::factory()->create(['title' => 'Nanti', 'due_on' => Carbon::parse('2026-09-20')]);
        $later->assignees()->attach($me->id, ['is_completed' => false]);

        $this->actingAs($me);

        $assignments = Livewire::test(MyTasks::class)->instance()->getAssignments();

        $this->assertSame('Nanti', $assignments->first()->task->title);
        $this->assertSame('Segera', $assignments->last()->task->title);
    }
}
