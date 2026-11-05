@php
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsRenderHook;

    $renderHookScopes = $this->getRenderHookScopes();
@endphp

<div class="flex flex-col gap-8">
    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_START, scopes: $renderHookScopes) }}

    <x-filament-panels::logo />

    <header class="flex flex-col gap-2">
        <h1 class="text-3xl font-bold tracking-tight text-balance text-gray-950 dark:text-white">
            {{ $this->getHeading() }}
        </h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $this->getSubheading() }}
        </p>
    </header>

    {{ $this->content }}

    <ul class="flex flex-col gap-3 rounded-2xl bg-gray-100/70 p-4 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
        <li class="flex items-start gap-3">
            <x-filament::icon :icon="Heroicon::OutlinedKey" class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400" />
            <span>Baru pertama masuk? Kamu akan diminta membuat kata sandi sendiri.</span>
        </li>
        <li class="flex items-start gap-3">
            <x-filament::icon :icon="Heroicon::OutlinedLifebuoy" class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400" />
            <span>Lupa kata sandi? Minta admin sekolah untuk mengatur ulang.</span>
        </li>
    </ul>

    <x-filament-actions::modals />

    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_PAGE_END, scopes: $renderHookScopes) }}
</div>
