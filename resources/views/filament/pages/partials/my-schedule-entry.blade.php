<div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
    <span
        @class([
            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
            'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => $entry['type'] === 'teaching',
            'bg-info-50 text-info-600 dark:bg-info-400/10 dark:text-info-400' => $entry['type'] === 'covering',
            'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => $entry['type'] === 'routine',
        ])
    >
        @if ($entry['type'] === 'routine')
            <x-filament::icon icon="heroicon-o-book-open" class="h-4.5 w-4.5" />
        @elseif ($entry['type'] === 'covering')
            <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4.5 w-4.5" />
        @else
            <x-filament::icon icon="heroicon-o-pencil-square" class="h-4.5 w-4.5" />
        @endif
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $entry['label'] }}</p>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $entry['subtitle'] }}</p>
        @if ($entry['note'])
            <p class="mt-0.5 text-xs font-semibold text-warning-600 dark:text-warning-400">{{ $entry['note'] }}</p>
        @endif
    </div>

    <div class="shrink-0 text-right text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
        <span>{{ $entry['start'] }}</span>
        <span class="text-gray-400">–</span>
        <span>{{ $entry['end'] }}</span>
    </div>
</div>
