<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ChangePassword;
use App\Http\Middleware\RequirePasswordChange;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('portal')
            ->path('')
            ->viteTheme('resources/css/filament/portal/theme.css')
            ->login()
            ->profile(ChangePassword::class, isSimple: false)
            ->font(
                "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
                provider: LocalFontProvider::class,
            )
            ->monoFont('JetBrains Mono')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->colors($this->colors())
            ->globalSearch(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? view('filament.dock')->render() : '',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequirePasswordChange::class,
            ]);
    }

    /**
     * "Bento Grid Tech Minimalist" palette: Apple-system aesthetic — white surfaces,
     * charcoal text (never pure black, per the design system's own rule), a purple
     * brand/CTA accent, and a soft blue secondary accent. `Color::hex()` auto-derives a
     * full 50-950 shade scale from one anchor, and auto-detects near-achromatic input
     * (like #1D1D1F) to zero out chroma for a clean neutral scale.
     *
     * 'gray' uses Filament's own `Color::Neutral` rather than `Color::hex('#1d1d1f')`:
     * `Color::hex()`'s lightness ramp is fixed regardless of how dark the anchor is (only
     * the hue carries over), so it placed shade 900 at ~40% lightness and 950 at ~28% —
     * a washed-out medium gray, not the near-black dark-mode surfaces this app actually
     * renders with (`dark:bg-gray-900`, `dark:bg-gray-950` throughout). `Color::Neutral`
     * is the same true-achromatic scale Tailwind ships, tuned all the way to a real
     * near-black at 950 (~14% lightness) — its light-end shades (50-400) are close enough
     * to the old ones that light mode is effectively unaffected.
     *
     * success/warning/danger aren't part of the supplied design spec (which is written
     * for a marketing site, not an admin panel's status semantics); I picked Apple's own
     * iOS/macOS system colors for them, since they fit this exact aesthetic and stay
     * under the spec's 80% saturation cap.
     *
     * @return array<string, array<int, string>>
     */
    private function colors(): array
    {
        return [
            'primary' => Color::hex('#9562e3'),
            'info' => Color::hex('#0071e3'),
            'gray' => Color::Neutral,
            'success' => Color::hex('#34c759'),
            'warning' => Color::hex('#ff9500'),
            'danger' => Color::hex('#ff3b30'),
        ];
    }
}
