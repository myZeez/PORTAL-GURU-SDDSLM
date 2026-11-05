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
    public function test_completed_records_can_only_be_changed_by_those_with_correction_authority(?Role $role): void
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
            // Waka Kurikulum alone may step in on daily records, always leaving a trace: it
            // corrects attendance, substitutions and assessments, and removes attendance,
            // journals, substitutions and assessments that were filled in wrongly. Everything
            // else stays with Super Admin.
            $isWaka = $role === Role::WakaKurikulum;
            $expectedUpdate = $isWaka && ($record instanceof TeacherAttendance || $record instanceof Substitution || $record instanceof Assessment);
            $expectedDelete = $isWaka && ($record instanceof TeacherAttendance || $record instanceof Journal || $record instanceof Substitution || $record instanceof Assessment);

            $this->assertSame($expectedUpdate, $actor->can('update', $record), $record::class.' update');
            $this->assertSame($expectedDelete, $actor->can('delete', $record), $record::class.' delete');
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
