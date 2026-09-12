<x-filament-panels::page>
    <div class="flex flex-col gap-2.5">
        @forelse ($this->getAssignments() as $assignee)
            @php
                $task = $assignee->task;
                $isOverdue = ! $assignee->is_completed && $task->due_on->isPast() && ! $task->due_on->isToday();
            @endphp
            <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <button
                    type="button"
                    wire:click="toggle({{ $assignee->id }})"
                    @class([
                        'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition',
                        'border-primary-600 bg-primary-600 text-white' => $assignee->is_completed,
                        'border-gray-300 dark:border-white/20' => ! $assignee->is_completed,
                    ])
                    aria-label="Tandai selesai"
                >
                    @if ($assignee->is_completed)
                        <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                    @endif
                </button>

                <div class="min-w-0 flex-1">
                    <p @class([
                        'text-sm font-bold text-gray-950 dark:text-white',
                        'line-through opacity-50' => $assignee->is_completed,
                    ])>
                        {{ $task->title }}
                    </p>
                    @if ($task->description)
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $task->description }}</p>
                    @endif
                    @if ($task->link)
                        <a href="{{ $task->link }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-bold text-primary-600 dark:text-primary-400">
                            Buka link →
                        </a>
                    @endif
                </div>

                <span
                    @class([
                        'shrink-0 text-xs font-bold whitespace-nowrap',
                        'text-danger-600 dark:text-danger-400' => $isOverdue,
                        'text-gray-700 dark:text-gray-300' => ! $isOverdue,
                    ])
                >
                    {{ $task->due_on->format('d/m/Y') }}
                </span>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Belum ada tugas untuk Anda.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
