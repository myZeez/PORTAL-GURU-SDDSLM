@php
    use App\Filament\Pages\Menu;
    use App\Filament\Pages\MySchedule;
    use Filament\Support\Icons\Heroicon;

    $menuUrl = Menu::getUrl();

    $items = [
        [
            'label' => 'Beranda',
            'icon' => Heroicon::OutlinedHome,
            'url' => route('filament.portal.pages.dashboard'),
            'active' => request()->routeIs('filament.portal.pages.dashboard'),
        ],
        [
            'label' => 'Absensi',
            'icon' => Heroicon::OutlinedClipboardDocumentCheck,
            'url' => $menuUrl,
            'active' => false,
        ],
        [
            'label' => 'Jurnal',
            'icon' => Heroicon::OutlinedBookOpen,
            'url' => $menuUrl,
            'active' => false,
        ],
        [
            'label' => 'Jadwal',
            'icon' => Heroicon::OutlinedCalendarDays,
            'url' => MySchedule::getUrl(),
            'active' => request()->routeIs('filament.portal.pages.my-schedule'),
        ],
        [
            'label' => 'Lainnya',
            'icon' => Heroicon::OutlinedSquares2x2,
            'url' => $menuUrl,
            'active' => request()->routeIs('filament.portal.pages.menu'),
        ],
    ];
@endphp

{{--
    Floating dock, phones only (lg:hidden — the desktop sidebar carries navigation from
    there up). Absensi/Jurnal still point at the module map, since those pages don't
    exist yet; they'll get their own routes as Fase 2 ships further.
--}}
<nav
    class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 lg:hidden"
    style="padding-bottom: max(env(safe-area-inset-bottom), 0px);"
    aria-label="Navigasi utama"
>
    <div
        class="mb-4 flex w-full max-w-sm items-center justify-around gap-1 rounded-[1.75rem] border border-white/80 bg-white/70 px-2 py-2 shadow-[0_18px_40px_-12px_rgba(16,40,30,0.25)] backdrop-blur-xl backdrop-saturate-150 dark:border-white/10 dark:bg-gray-900/70"
    >
        @foreach ($items as $item)
            <a
                href="{{ $item['url'] }}"
                aria-label="{{ $item['label'] }}"
                aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                class="flex flex-1 items-center justify-center py-1"
            >
                <span
                    @class([
                        'flex h-11 items-center justify-center rounded-2xl transition',
                        'w-13 bg-primary-600 text-white shadow-lg shadow-primary-600/30' => $item['active'],
                        'w-11 text-gray-500 dark:text-gray-400' => ! $item['active'],
                    ])
                >
                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                </span>
            </a>
        @endforeach
    </div>
</nav>
