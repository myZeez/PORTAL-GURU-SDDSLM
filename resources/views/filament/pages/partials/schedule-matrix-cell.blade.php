@if ($cell === null)
    <div class="px-2 py-1 text-center text-xs text-gray-300 dark:text-gray-700">—</div>
@elseif ($cell['type'] === 'filtered')
    <div class="px-2 py-1 text-center text-xs text-gray-300 dark:text-gray-700" aria-hidden="true">·</div>
@elseif ($cell['type'] === 'empty')
    <div class="rounded-lg border border-dashed border-gray-300 px-2 py-1 text-center text-[0.65rem] font-medium text-gray-400 dark:border-white/15 dark:text-gray-500">
        Kosong
    </div>
@elseif ($cell['type'] === 'routine')
    <div class="rounded-lg bg-gray-100 px-2 py-1 text-center text-[0.65rem] font-semibold text-gray-500 dark:bg-white/5 dark:text-gray-400">
        {{ $cell['label'] }}
    </div>
@else
    <div
        @class([
            'rounded-lg px-2 py-1 text-center leading-tight',
            'bg-danger-100 ring-2 ring-danger-500 dark:bg-danger-400/20' => $cell['clashes'] !== [],
            'bg-primary-100 ring-2 ring-primary-500 dark:bg-primary-400/20' => $cell['clashes'] === [] && $cell['mine'],
            'bg-gray-100 dark:bg-white/5' => $cell['clashes'] === [] && ! $cell['mine'],
        ])
        title="{{ $cell['title'] }}{{ $cell['clashes'] !== [] ? ' — BENTROK: '.implode(', ', array_map(fn ($kind) => match ($kind) { 'teacher' => 'guru ganda', 'classroom' => 'kelas ganda', default => 'jam rutinitas' }, $cell['clashes'])) : '' }}"
    >
        <div class="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">
            {{ $cell['subject'] }}
        </div>
        <div class="text-[0.65rem] text-gray-500 dark:text-gray-400">
            {{ $cell['secondary'] }}
            @if ($cell['clashes'] !== [])
                <span class="font-bold text-danger-600 dark:text-danger-400">· bentrok</span>
            @endif
        </div>
    </div>
@endif
