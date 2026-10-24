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

                @if ($detail = $this->getDetail())
                    <div class="rounded-2xl border border-primary-200 bg-primary-50/50 p-4 dark:border-primary-400/20 dark:bg-primary-400/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-gray-950 dark:text-white">Detail Jurnal</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tersimpan dan terkunci</p>
                            </div>
                            <button type="button" wire:click="closeDetail" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Tutup</button>
                        </div>

                        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tanggal</p>
                                <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail->date->translatedFormat('l, d F Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Kelas & Jam</p>
                                <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail->schedule->classroom->label }} · {{ $detail->schedule->timeSlot->label }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Materi</p>
                                <p class="mt-0.5 font-semibold text-gray-950 dark:text-white">{{ $detail->topic }}</p>
                            </div>
                            @if (filled($detail->pages))
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Halaman</p>
                                    <p class="mt-0.5 text-gray-950 dark:text-white">{{ $detail->pages }}</p>
                                </div>
                            @endif
                            @if (filled($detail->resource_url))
                                <div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Modul/RPP</p>
                                    <a href="{{ $detail->resource_url }}" target="_blank" rel="noopener noreferrer" class="mt-0.5 inline-block font-semibold text-primary-600 hover:underline dark:text-primary-400">Buka tautan</a>
                                </div>
                            @endif
                            @if (filled($detail->description))
                                <div class="sm:col-span-2">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Deskripsi</p>
                                    <p class="mt-0.5 whitespace-pre-line text-gray-950 dark:text-white">{{ $detail->description }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-4 gap-2">
                            @foreach (['Hadir' => $detail->students_present, 'Izin' => $detail->students_permitted, 'Sakit' => $detail->students_sick, 'Alpa' => $detail->students_absent] as $label => $count)
                                <div class="rounded-xl bg-white/80 px-2 py-2 text-center dark:bg-gray-900/60">
                                    <p class="text-base font-bold text-gray-950 dark:text-white">{{ $count }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

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
                            <button type="button" wire:click="showDetail({{ $entry->id }})" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Lihat detail</button>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Terkunci</span>
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
