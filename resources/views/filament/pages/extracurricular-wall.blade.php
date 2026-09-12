<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if ($this->canPost())
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                {{ $this->form }}
            </div>
        @endif

        <div class="flex flex-col gap-3">
            @forelse ($this->getAnnouncements() as $announcement)
                <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $announcement->title }}</p>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $announcement->creator?->name ?? '—' }} · {{ $announcement->created_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        </div>

                        @if ($this->canDeleteAnnouncement($announcement))
                            <button
                                type="button"
                                wire:click="deleteAnnouncement({{ $announcement->id }})"
                                wire:confirm="Hapus pengumuman ini beserta seluruh komentarnya?"
                                class="shrink-0 text-xs font-bold text-danger-600 hover:text-danger-500 dark:text-danger-400"
                            >
                                Hapus
                            </button>
                        @endif
                    </div>

                    @if ($announcement->image_url)
                        <img src="{{ $announcement->image_url }}" alt="" class="max-h-72 w-full rounded-xl object-cover" />
                    @endif

                    <p class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $announcement->body }}</p>

                    <div class="flex flex-col gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                        @forelse ($announcement->comments as $comment)
                            <div class="flex items-start justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-gray-950 dark:text-white">
                                        {{ $comment->user?->name ?? '—' }}
                                        <span class="font-normal text-gray-400">· {{ $comment->created_at->diffForHumans() }}</span>
                                    </p>
                                    <p class="text-xs text-gray-700 dark:text-gray-300">{{ $comment->body }}</p>
                                </div>

                                @if ($this->canDeleteComment($comment))
                                    <button
                                        type="button"
                                        wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="Hapus komentar ini?"
                                        class="shrink-0 text-[11px] font-bold text-danger-600 hover:text-danger-500 dark:text-danger-400"
                                    >
                                        Hapus
                                    </button>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 dark:text-gray-500">Belum ada komentar.</p>
                        @endforelse

                        <form wire:submit.prevent="postComment({{ $announcement->id }})" class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model="commentBodies.{{ $announcement->id }}"
                                placeholder="Tulis komentar…"
                                class="fi-input block w-full rounded-lg border-none bg-white text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                            />
                            <x-filament::button type="submit" size="sm">Kirim</x-filament::button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada pengumuman ekstrakurikuler.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
