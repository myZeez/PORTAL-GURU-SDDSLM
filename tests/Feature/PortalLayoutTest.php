<?php

namespace Tests\Feature;

use App\Filament\Pages\Menu;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('filament.portal.auth.login'));
    }

    public function test_the_floating_dock_does_not_appear_on_the_login_page(): void
    {
        $this->get(route('filament.portal.auth.login'))
            ->assertOk()
            ->assertDontSee('aria-label="Navigasi utama"', escape: false);
    }

    public function test_the_dashboard_shows_the_school_brand(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Portal Guru')
            ->assertSee('SD Islam Darussalam');
    }

    public function test_the_floating_dock_lists_the_five_primary_destinations(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('Absensi')
            ->assertSee('Jurnal')
            ->assertSee('Jadwal')
            ->assertSee('Lainnya')
            ->assertSeeInOrder([
                'aria-label="Navigasi utama"',
                route('filament.portal.pages.dashboard'),
                Menu::getUrl(),
            ], escape: false);
    }

    public function test_the_module_map_lists_every_phase_and_module(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(Menu::getUrl())
            ->assertOk()
            ->assertSee('Fase 1 — Fondasi')
            ->assertSee('Guru & Staf')
            ->assertSee(UserResource::getUrl(), escape: false)
            ->assertSee('Fase 2 — Modul Harian')
            ->assertSee('Absensi Guru')
            ->assertSee('Fase 4 — Monitoring')
            ->assertSee('Monev');
    }
}
