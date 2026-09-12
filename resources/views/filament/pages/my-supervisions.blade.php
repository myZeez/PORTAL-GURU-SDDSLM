<x-filament-panels::page>
    <div class="flex flex-col gap-2.5">
        @forelse ($this->getSupervisions() as $supervision)
            @php $upcoming = $this->isUpcoming($supervision); @endphp
            <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <span
                    @class([
                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                        'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => $upcoming,
                        'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => ! $upcoming,
                    ])
                >
                    <x-filament::icon icon="heroicon-o-eye" class="h-4.5 w-4.5" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $supervision->subject->name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $supervision->location }} · Supervisor: {{ $supervision->supervisor->name }}
                    </p>
                </div>

                <span class="shrink-0 text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                    {{ $supervision->date->format('d/m/Y') }}
                </span>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Belum ada jadwal supervisi untuk Anda.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
