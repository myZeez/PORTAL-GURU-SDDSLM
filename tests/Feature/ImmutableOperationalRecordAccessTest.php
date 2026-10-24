<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Cocurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\Journal;
use App\Models\Substitution;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImmutableOperationalRecordAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: Role|null}>
     */
    public static function actorsWithoutCorrectionAuthority(): array
    {
        return [
            'guru' => [null],
            'kepala sekolah' => [Role::KepalaSekolah],
            'waka kurikulum' => [Role::WakaKurikulum],
            'admin kurikulum' => [Role::AdminKurikulum],
            'waka sarpras' => [Role::WakaSarpras],
            'koordinator ekskul' => [Role::KoordinatorEkskul],
        ];
    }

    #[DataProvider('actorsWithoutCorrectionAuthority')]
    public function test_only_super_admin_can_change_completed_operational_records(?Role $role): void
    {
        $actor = $role === null
            ? User::factory()->create()
            : User::factory()->withRoles($role)->create();

        $records = [
            TeacherAttendance::factory()->create(),
            Journal::factory()->create(),
            Substitution::factory()->create(),
            Assessment::factory()->create(),
            ExtracurricularAttendance::factory()->create(),
            Cocurricular::factory()->create(),
        ];

        foreach ($records as $record) {
            // Waka Kurikulum alone may correct attendance (with a reason and an audit
            // entry); every other record, and every deletion, stays with Super Admin.
            $isAttendanceCorrectedByWaka = $record instanceof TeacherAttendance && $role === Role::WakaKurikulum;

            $this->assertSame($isAttendanceCorrectedByWaka, $actor->can('update', $record));
            $this->assertFalse($actor->can('delete', $record));
        }
    }

    public function test_super_admin_can_correct_completed_operational_records(): void
    {
        $superAdmin = User::factory()->withRoles(Role::SuperAdmin)->create();

        $records = [
            TeacherAttendance::factory()->create(),
            Journal::factory()->create(),
            Substitution::factory()->create(),
            Assessment::factory()->create(),
            ExtracurricularAttendance::factory()->create(),
            Cocurricular::factory()->create(),
        ];

        foreach ($records as $record) {
            $this->assertTrue($superAdmin->can('update', $record));
            $this->assertTrue($superAdmin->can('delete', $record));
        }
    }
}
