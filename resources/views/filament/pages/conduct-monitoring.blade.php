<x-filament-panels::page>
    @if ($this->result->status)
        <div class="mb-4 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <x-filament::badge :color="$this->result->status->getColor()" size="lg">
                {{ $this->result->status->getLabel() }}
            </x-filament::badge>
            <span class="text-sm font-bold text-gray-950 dark:text-white">Skor: {{ $this->result->score }}%</span>
        </div>
    @endif

    {{ $this->form }}
</x-filament-panels::page>
