<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\ExtracurricularWall;
use App\Models\ExtracurricularAnnouncement;
use App\Models\ExtracurricularAnnouncementComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtracurricularWallAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_signed_in_user_can_open_the_wall(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(ExtracurricularWall::getUrl())
            ->assertOk();
    }

    public function test_a_plain_teacher_cannot_post_an_announcement(): void
    {
        $this->assertFalse(User::factory()->create()->can('create', ExtracurricularAnnouncement::class));
    }

    public function test_the_ekskul_coordinator_can_post_an_announcement(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KoordinatorEkskul)->create());

        Livewire::test(ExtracurricularWall::class)
            ->set('data.title', 'Latihan Pramuka Diliburkan')
            ->set('data.body', 'Latihan minggu ini diliburkan karena hujan.')
            ->call('postAnnouncement');

        $this->assertDatabaseHas('extracurricular_announcements', ['title' => 'Latihan Pramuka Diliburkan']);
    }

    public function test_any_signed_in_user_can_comment_on_an_announcement(): void
    {
        $announcement = ExtracurricularAnnouncement::factory()->create();
        $commenter = User::factory()->create();

        $this->actingAs($commenter);

        Livewire::test(ExtracurricularWall::class)
            ->set("commentBodies.{$announcement->id}", 'Siap, terima kasih infonya.')
            ->call('postComment', $announcement->id);

        $this->assertDatabaseHas('extracurricular_announcement_comments', [
            'extracurricular_announcement_id' => $announcement->id,
            'user_id' => $commenter->id,
            'body' => 'Siap, terima kasih infonya.',
        ]);
    }

    public function test_a_teacher_cannot_delete_someone_elses_announcement(): void
    {
        $announcement = ExtracurricularAnnouncement::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider);

        Livewire::test(ExtracurricularWall::class)
            ->call('deleteAnnouncement', $announcement->id)
            ->assertForbidden();

        $this->assertDatabaseHas('extracurricular_announcements', ['id' => $announcement->id]);
    }

    public function test_a_user_can_delete_their_own_comment(): void
    {
        $author = User::factory()->create();
        $comment = ExtracurricularAnnouncementComment::factory()->create(['user_id' => $author->id]);

        $this->actingAs($author);

        Livewire::test(ExtracurricularWall::class)
            ->call('deleteComment', $comment->id);

        $this->assertDatabaseMissing('extracurricular_announcement_comments', ['id' => $comment->id]);
    }
}
