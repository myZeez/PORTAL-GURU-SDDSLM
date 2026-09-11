<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
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
            ->font('Nunito')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->colors($this->colors())
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Placeholder until the real dashboard (Fase 2, item 13) replaces it.
                AccountWidget::class,
            ])
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
            ]);
    }

    /**
     * Custom OKLCH palettes matching the "Ceria" direction picked for Portal Guru: the
     * same hues used throughout the design mockups (green 152, rose 18, amber 80, blue
     * 250), each shade chosen to stay inside the sRGB gamut so nothing gets clipped.
     *
     * @return array<string, array<int, string>>
     */
    private function colors(): array
    {
        return [
            'primary' => $this->palette(152),
            'success' => $this->palette(152),
            'danger' => $this->palette(18, [
                50 => 0.010, 100 => 0.025, 200 => 0.052, 300 => 0.088, 400 => 0.150,
                500 => 0.160, 600 => 0.150, 700 => 0.130, 800 => 0.100, 900 => 0.080, 950 => 0.055,
            ]),
            'warning' => $this->palette(80, [
                50 => 0.020, 100 => 0.045, 200 => 0.075, 300 => 0.115, 400 => 0.150,
                500 => 0.129, 600 => 0.104, 700 => 0.087, 800 => 0.075, 900 => 0.063, 950 => 0.047,
            ]),
            'info' => $this->palette(250, [
                50 => 0.010, 100 => 0.025, 200 => 0.051, 300 => 0.083, 400 => 0.140,
                500 => 0.160, 600 => 0.142, 700 => 0.119, 800 => 0.100, 900 => 0.080, 950 => 0.055,
            ]),
            'gray' => Color::Slate,
        ];
    }

    /**
     * Build an 11-shade OKLCH palette for a hue, using chroma values already verified to
     * stay in the sRGB gamut for that hue (computed once; see docs/design for the script).
     *
     * @param  array<int, float>|null  $chroma  Per-shade chroma override, keyed by shade.
     * @return array<int, string>
     */
    private function palette(int $hue, ?array $chroma = null): array
    {
        $lightness = [
            50 => 0.979, 100 => 0.950, 200 => 0.900, 300 => 0.840, 400 => 0.740,
            500 => 0.620, 600 => 0.500, 700 => 0.420, 800 => 0.360, 900 => 0.300, 950 => 0.220,
        ];

        $chroma ??= [
            50 => 0.020, 100 => 0.045, 200 => 0.075, 300 => 0.115, 400 => 0.150,
            500 => 0.160, 600 => 0.132, 700 => 0.111, 800 => 0.095, 900 => 0.080, 950 => 0.055,
        ];

        $shades = [];

        foreach ($lightness as $shade => $l) {
            $shades[$shade] = "oklch({$l} {$chroma[$shade]} {$hue})";
        }

        return $shades;
    }
}
