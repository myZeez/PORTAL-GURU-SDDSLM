<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Curricula\CurriculumResource;
use App\Filament\Resources\Curricula\Pages\ManageCurricula;
use App\Models\Curriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurriculumAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_open_the_curriculum_library(): void
    {
        foreach ([
            User::factory()->create(),
            User::factory()->withRoles(Role::KepalaSekolah)->create(),
            User::factory()->withRoles(Role::WakaKurikulum)->create(),
        ] as $user) {
            $this->actingAs($user)
                ->get(CurriculumResource::getUrl())
                ->assertOk();
        }
    }

    public function test_a_plain_teacher_cannot_create_a_document(): void
    {
        $teacher = User::factory()->create();

        $this->assertFalse($teacher->can('create', Curriculum::class));
    }

    public function test_an_administrator_can_add_a_document(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageCurricula::class)
            ->callAction('create', data: [
                'title' => 'Kurikulum Merdeka 2026',
                'drive_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz1234/view?usp=sharing',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('curricula', ['title' => 'Kurikulum Merdeka 2026']);
    }

    public function test_a_link_that_is_not_a_google_drive_link_is_rejected(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        Livewire::test(ManageCurricula::class)
            ->callAction('create', data: [
                'title' => 'Dokumen Aneh',
                'drive_url' => 'https://example.com/some-file.pdf',
            ])
            ->assertHasActionErrors(['drive_url']);
    }

    public function test_it_extracts_the_file_id_from_common_drive_link_shapes(): void
    {
        $this->assertSame(
            'ABC123',
            Curriculum::extractDriveFileId('https://drive.google.com/file/d/ABC123/view?usp=sharing'),
        );
        $this->assertSame(
            'XYZ789',
            Curriculum::extractDriveFileId('https://docs.google.com/document/d/XYZ789/edit'),
        );
        $this->assertSame(
            'OLDSTYLE',
            Curriculum::extractDriveFileId('https://drive.google.com/open?id=OLDSTYLE'),
        );
        $this->assertNull(Curriculum::extractDriveFileId('https://example.com/not-drive.pdf'));
    }

    public function test_thumbnail_preview_and_download_urls_are_derived_from_the_drive_link(): void
    {
        $curriculum = Curriculum::factory()->create([
            'drive_url' => 'https://drive.google.com/file/d/FILEID123/view?usp=sharing',
        ]);

        $this->assertSame('https://drive.google.com/thumbnail?id=FILEID123&sz=w400', $curriculum->thumbnail_url);
        $this->assertSame('https://drive.google.com/file/d/FILEID123/preview', $curriculum->preview_url);
        $this->assertSame('https://drive.google.com/uc?export=download&id=FILEID123', $curriculum->download_url);
    }
}
