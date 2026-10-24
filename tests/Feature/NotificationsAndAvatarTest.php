<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\OutingStatus;
use App\Enums\Role;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\OutingClasses\OutingClassResource;
use App\Filament\Resources\PidReservations\PidReservationResource;
use App\Models\Classroom;
use App\Models\OutingClass;
use App\Models\PidReservation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsAndAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_early_leave_is_called_pulang_awal_everywhere_users_see_it(): void
    {
        $this->assertSame('Pulang Awal', AttendanceStatus::PulangCepat->getLabel());
    }

    public function test_initials_ignore_degrees_and_honorifics(): void
    {
        $this->assertSame('KF', User::initialsFor('Kiki Fauziah, S.Si., M.Pd., Gr.'));
        $this->assertSame('AC', User::initialsFor('Andi Cindy Rahmadani Efendi, M.Pd., Gr.'));
        $this->assertSame('BR', User::initialsFor('Drs. H. Basuki Rahmat, S.Pd'));
        $this->assertSame('M', User::initialsFor('Madonna'));
        $this->assertSame('?', User::initialsFor(''));
    }

    public function test_a_user_without_a_photo_gets_a_local_initials_avatar(): void
    {
        $user = User::factory()->create(['name' => 'Kiki Fauziah, S.Si., M.Pd., Gr.', 'photo_path' => null]);

        $avatar = Filament::getUserAvatarUrl($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $avatar);
        $this->assertStringContainsString('>KF<', base64_decode(substr($avatar, strlen('data:image/svg+xml;base64,'))));
    }

    public function test_a_missing_photo_file_falls_back_to_initials_instead_of_a_broken_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'Kiki Fauziah', 'photo_path' => 'avatars/hilang.png']);

        $this->assertNull($user->getFilamentAvatarUrl());
        $this->assertStringStartsWith('data:image/svg+xml', Filament::getUserAvatarUrl($user));
    }

    public function test_an_uploaded_photo_is_served_through_the_application(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/kiki.png', 'isi-gambar');
        $user = User::factory()->create(['photo_path' => 'avatars/kiki.png']);

        $url = $user->getFilamentAvatarUrl();

        $this->assertStringContainsString("/avatars/{$user->id}", $url);

        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_avatar_route_needs_a_login_and_a_real_photo(): void
    {
        Storage::fake('public');
        $withoutPhoto = User::factory()->create(['photo_path' => null]);

        $this->get(route('avatars.show', $withoutPhoto))->assertRedirect();

        $this->actingAs(User::factory()->create())
            ->get(route('avatars.show', $withoutPhoto))
            ->assertNotFound();
    }

    public function test_the_notification_bell_is_part_of_the_header(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('database-notifications', escape: false);
    }

    public function test_a_new_outing_request_notifies_the_people_who_approve_it(): void
    {
        $waka = User::factory()->withRoles(Role::WakaKurikulum)->create();
        $superAdmin = User::factory()->withRoles(Role::SuperAdmin)->create();
        $adminKurikulum = User::factory()->withRoles(Role::AdminKurikulum)->create();
        $teacher = User::factory()->create();
        $classroom = Classroom::factory()->create();

        OutingClass::factory()->create(['requested_by' => $teacher->id, 'classroom_id' => $classroom->id]);

        $this->assertSame(1, $waka->notifications()->count());
        $this->assertSame(1, $superAdmin->notifications()->count());
        $this->assertSame(0, $adminKurikulum->notifications()->count());
        $this->assertSame(0, $teacher->notifications()->count());
        $this->assertStringContainsString('menunggu persetujuan', $waka->notifications()->first()->data['title']);
    }

    public function test_a_new_pid_reservation_notifies_waka_sarpras_but_not_the_requester(): void
    {
        $sarpras = User::factory()->withRoles(Role::WakaSarpras)->create();
        $waka = User::factory()->withRoles(Role::WakaKurikulum)->create();

        PidReservation::factory()->create(['requested_by' => $waka->id]);

        $this->assertSame(1, $sarpras->notifications()->count());
        $this->assertSame(0, $waka->notifications()->count());
    }

    public function test_an_approver_does_not_notify_themselves_when_they_submit(): void
    {
        $waka = User::factory()->withRoles(Role::WakaKurikulum)->create();

        OutingClass::factory()->create(['requested_by' => $waka->id]);

        $this->assertSame(0, $waka->notifications()->count());
    }

    public function test_the_pending_badge_counts_real_requests_only_for_approvers(): void
    {
        OutingClass::factory()->count(2)->create(['status' => OutingStatus::Menunggu]);
        OutingClass::factory()->create(['status' => OutingStatus::Disetujui]);
        PidReservation::factory()->count(3)->create(['status' => OutingStatus::Menunggu]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());
        $this->assertSame('2', OutingClassResource::getNavigationBadge());
        $this->assertNull(PidReservationResource::getNavigationBadge());

        $this->actingAs(User::factory()->withRoles(Role::WakaSarpras)->create());
        $this->assertSame('3', PidReservationResource::getNavigationBadge());
        $this->assertNull(OutingClassResource::getNavigationBadge());

        $this->actingAs(User::factory()->create());
        $this->assertNull(OutingClassResource::getNavigationBadge());
        $this->assertNull(PidReservationResource::getNavigationBadge());
    }

    public function test_the_badge_disappears_once_nothing_is_waiting(): void
    {
        OutingClass::factory()->create(['status' => OutingStatus::Disetujui]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $this->assertNull(OutingClassResource::getNavigationBadge());
    }
}
