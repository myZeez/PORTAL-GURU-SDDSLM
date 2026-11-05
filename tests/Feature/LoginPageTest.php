<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_welcomes_users_and_shows_the_school_illustration(): void
    {
        $this->get(route('filament.portal.auth.login'))
            ->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertSee('Baru pertama masuk?')
            ->assertSee('Lupa kata sandi?')
            ->assertSee('images/illustrations/teacher-team-hero.webp', false);
    }

    public function test_the_illustration_exists_and_is_light_enough_for_a_page_everyone_opens(): void
    {
        $path = public_path('images/illustrations/teacher-team-hero.webp');

        $this->assertFileExists($path);
        $this->assertLessThan(300 * 1024, filesize($path));
    }

    public function test_the_dashboard_uses_the_same_illustration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('images/illustrations/teacher-team-hero.webp', false);
    }

    public function test_a_user_can_still_sign_in_from_the_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_refused_with_a_message_on_the_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'salah-sekali'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
