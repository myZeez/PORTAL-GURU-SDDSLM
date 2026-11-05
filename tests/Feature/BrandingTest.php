<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_panel_page_carries_the_school_logo_as_its_tab_icon(): void
    {
        $login = $this->get(route('filament.portal.auth.login'))->assertOk();

        $this->actingAs(User::factory()->create());
        $dashboard = $this->get('/')->assertOk();

        foreach ([$login, $dashboard] as $page) {
            $page->assertSee('images/brand/favicon-32.png', false)
                ->assertSee('rel="icon" type="image/png" sizes="192x192"', false)
                ->assertSee('rel="apple-touch-icon"', false);
        }
    }

    public function test_the_icon_files_are_real_images_of_the_promised_sizes(): void
    {
        foreach (['favicon-32' => 32, 'favicon-192' => 192, 'apple-touch-icon' => 180, 'logo-sdid' => 512] as $name => $size) {
            $path = public_path("images/brand/{$name}.png");

            $this->assertFileExists($path);
            $this->assertSame([$size, $size], array_slice(getimagesize($path), 0, 2), $name);
        }
    }

    public function test_the_default_favicon_ico_is_the_school_logo_too(): void
    {
        $bytes = (string) file_get_contents(public_path('favicon.ico'));

        // An ICO file starts with the reserved word 0, the type 1 and the number of images.
        $this->assertSame([0, 1, 3], array_values(unpack('v3', substr($bytes, 0, 6))));
        $this->assertGreaterThan(1000, strlen($bytes));
    }
}
