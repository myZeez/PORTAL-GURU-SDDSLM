<?php

namespace App\Filament\Pages;

use App\Models\TaskAssignee;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class MyTasks extends Page
{
    protected string $view = 'filament.pages.my-tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Tugas Saya';

    protected static ?string $title = 'Tugas Saya';

    protected static ?int $navigationSort = 6;

    /**
     * Get the signed-in user's own task assignments, unfinished ones first and then
     * soonest deadline first.
     *
     * @return Collection<int, TaskAssignee>
     */
    public function getAssignments(): Collection
    {
        return TaskAssignee::query()
            ->where('user_id', auth()->id())
            ->with('task')
            ->get()
            ->sortBy([
                fn (TaskAssignee $assignee): int => $assignee->is_completed ? 1 : 0,
                fn (TaskAssignee $assignee): string => $assignee->task->due_on->toDateString(),
            ])
            ->values();
    }

    public function toggle(int $taskAssigneeId): void
    {
        $assignee = TaskAssignee::query()
            ->where('user_id', auth()->id())
            ->find($taskAssigneeId);

        if (! $assignee) {
            return;
        }

        $assignee->update([
            'is_completed' => ! $assignee->is_completed,
            'completed_at' => $assignee->is_completed ? null : Carbon::now(),
        ]);
    }
}
