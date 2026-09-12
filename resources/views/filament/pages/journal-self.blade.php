<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if (! $this->isApplicable())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Kepala Sekolah tidak mengisi jurnal mengajar. Lihat riwayat di menu Jurnal Guru.
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                {{ $this->form }}
            </div>

            <div class="flex flex-col gap-2.5">
                <p class="text-sm font-bold text-gray-950 dark:text-white">Riwayat 14 Hari Terakhir</p>

                @forelse ($this->getHistory() as $entry)
                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-pencil-square" class="h-4.5 w-4.5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                                {{ $entry->schedule->classroom->label }} — {{ $entry->schedule->subject->name }}
                            </p>
                            <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $entry->topic }} · H:{{ $entry->students_present }} I:{{ $entry->students_permitted }} S:{{ $entry->students_sick }} A:{{ $entry->students_absent }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {{ $entry->date->format('d/m/Y') }}
                            </span>
                            <div class="flex gap-2">
                                <button type="button" wire:click="edit({{ $entry->id }})" class="text-xs font-bold text-primary-600 dark:text-primary-400">
                                    Ubah
                                </button>
                                <button
                                    type="button"
                                    wire:click="delete({{ $entry->id }})"
                                    wire:confirm="Yakin hapus jurnal ini?"
                                    class="text-xs font-bold text-danger-600 dark:text-danger-400"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Belum ada riwayat jurnal.
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
