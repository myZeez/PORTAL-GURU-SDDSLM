<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\Menu;
use App\Filament\Resources\Journals\JournalResource;
use App\Filament\Resources\Substitutions\SubstitutionResource;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
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

    public function test_the_module_map_hides_administrative_modules_from_a_plain_teacher(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(Menu::getUrl())
            ->assertOk()
            ->assertSee('Saya')
            ->assertSee('Jadwal Saya')
            ->assertSee('Program & Layanan')
            ->assertDontSee(TeacherAttendanceResource::getUrl(), escape: false)
            ->assertDontSee(JournalResource::getUrl(), escape: false)
            ->assertDontSee(SubstitutionResource::getUrl(), escape: false)
            ->assertDontSee(UserResource::getUrl(), escape: false)
            ->assertDontSee('Data Master')
            ->assertSee('Monitoring')
            ->assertSee('Monev');
    }

    public function test_the_module_map_shows_master_data_to_curriculum_management(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create())
            ->get(Menu::getUrl())
            ->assertOk()
            ->assertSee('Data Master')
            ->assertSee(UserResource::getUrl(), escape: false)
            ->assertSee(TeacherAttendanceResource::getUrl(), escape: false);
    }
}
