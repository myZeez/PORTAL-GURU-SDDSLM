@php
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;

    $renderHookScopes = $livewire?->getRenderHookScopes();

    $highlights = [
        ['icon' => Heroicon::OutlinedCalendarDays, 'label' => 'Jadwal & Matriks', 'position' => 'left-6 top-[14%]'],
        ['icon' => Heroicon::OutlinedClipboardDocumentCheck, 'label' => 'Absensi Guru', 'position' => 'right-6 top-[38%]'],
        ['icon' => Heroicon::OutlinedBookOpen, 'label' => 'Jurnal Mengajar', 'position' => 'left-8 bottom-[20%]'],
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="flex min-h-dvh flex-col lg:flex-row">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <div class="flex flex-1 flex-col px-4 py-8 sm:px-10 lg:flex-none lg:basis-[46%] lg:px-14 xl:px-24">
            <main
                id="fi-main-content"
                tabindex="-1"
                class="my-auto w-full max-w-md self-center rounded-3xl bg-white p-6 shadow-xs ring-1 ring-gray-950/5 sm:p-10 lg:bg-transparent lg:p-0 lg:shadow-none lg:ring-0 dark:bg-gray-900 dark:ring-white/10 lg:dark:bg-transparent"
            >
                {{ $slot }}
            </main>

            <p class="mt-8 text-center text-xs text-gray-500 dark:text-gray-400">
                &copy; {{ now()->year }} SD Islam Darussalam Palangka Raya
            </p>
        </div>

        <aside
            aria-label="Tentang Portal Guru"
            class="relative isolate hidden overflow-hidden rounded-4xl bg-linear-to-br from-primary-200/80 via-primary-100 to-primary-50 lg:sticky lg:top-4 lg:m-4 lg:ml-0 lg:flex lg:h-[calc(100dvh-2rem)] lg:flex-1 lg:flex-col lg:self-start dark:from-primary-950 dark:via-gray-900 dark:to-gray-950"
        >
            {{-- Kawung, the batik lattice worn in the illustration, fading out from the top right. --}}
            <svg
                class="absolute inset-0 -z-10 size-full text-primary-700/20 [mask-image:radial-gradient(ellipse_at_80%_15%,black,transparent_70%)] dark:text-primary-300/15"
                aria-hidden="true"
            >
                <defs>
                    <pattern id="kawung" width="40" height="40" patternUnits="userSpaceOnUse">
                        <g fill="none" stroke="currentColor" stroke-width="1">
                            <ellipse cx="20" cy="10" rx="5" ry="10" />
                            <ellipse cx="20" cy="30" rx="5" ry="10" />
                            <ellipse cx="10" cy="20" rx="10" ry="5" />
                            <ellipse cx="30" cy="20" rx="10" ry="5" />
                        </g>
                        <circle cx="20" cy="20" r="1.4" fill="currentColor" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#kawung)" />
            </svg>

            <div class="absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-primary-300/50 blur-3xl dark:bg-primary-600/25"></div>

            <div class="flex max-w-lg flex-col gap-4 p-10 xl:p-14">
                <p class="text-xs font-semibold tracking-widest text-primary-700 uppercase dark:text-primary-300">Portal Guru</p>
                <p class="text-4xl leading-[1.1] font-bold tracking-tight text-balance text-gray-950 xl:text-5xl dark:text-white">
                    Jadwal, absensi, dan jurnal dalam satu tempat.
                </p>
                <p class="max-w-sm text-base text-gray-700 dark:text-gray-300">
                    Catatan kerja harian guru dan staf, dari jadwal pagi sampai jurnal mengajar.
                </p>
            </div>

            <div class="relative min-h-0 flex-1">
                <img
                    src="{{ asset('images/illustrations/teacher-team-hero.webp') }}"
                    alt="Tiga guru SD Islam Darussalam berseragam batik ungu"
                    width="932"
                    height="1200"
                    loading="lazy"
                    decoding="async"
                    class="absolute bottom-0 left-1/2 h-full w-auto max-w-none -translate-x-1/2 object-contain object-bottom motion-safe:animate-hero-rise"
                />

                @foreach ($highlights as $highlight)
                    <div class="absolute {{ $highlight['position'] }} hidden items-center gap-2.5 rounded-2xl bg-white/80 py-2.5 pr-4 pl-3 text-sm font-semibold text-gray-900 shadow-md ring-1 ring-gray-950/5 backdrop-blur xl:flex dark:bg-gray-900/80 dark:text-white dark:ring-white/10">
                        <span class="flex size-8 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-400/15 dark:text-primary-300">
                            <x-filament::icon :icon="$highlight['icon']" class="size-5" />
                        </span>
                        {{ $highlight['label'] }}
                    </div>
                @endforeach
            </div>
        </aside>

        {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
