<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\DailyRoutine;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_school_master_data_from_sipeka(): void
    {
        $this->seed();

        $this->assertSame(34, User::query()->whereNotNull('code')->count());
        $this->assertSame(14, Classroom::query()
            ->whereNotNull('homeroom_teacher_id')
            ->whereNotNull('assistant_teacher_id')
            ->count());
        $this->assertSame(18, Subject::query()->count());
        $this->assertSame(2, Subject::query()->where('counts_toward_teaching_load', false)->count());
        $this->assertSame(19, TimeSlot::query()->count());
        $this->assertSame(40, DailyRoutine::query()->count());
        $this->assertSame('2026/2027 Ganjil', Semester::current()?->name);
    }

    public function test_it_assigns_the_roles_decided_by_the_school(): void
    {
        $this->seed();

        $roleHolders = User::query()
            ->whereNotNull('code')
            ->get()
            ->filter(fn (User $user): bool => $user->roles->isNotEmpty())
            ->mapWithKeys(fn (User $user): array => [$user->code => $user->roles->all()]);

        $this->assertEquals([
            'JJ' => [Role::KepalaSekolah],
            'KN' => [Role::WakaKurikulum],
            'MK' => [Role::KoordinatorEkskul],
            'JW' => [Role::WakaSarpras],
            'FB' => [Role::AdminKurikulum],
        ], $roleHolders->all());

        $this->assertSame('FB', Classroom::query()->where('code', 'II-A')->firstOrFail()->homeroomTeacher->code);
    }

    public function test_it_can_run_again_without_duplicating_or_overwriting_data(): void
    {
        $this->seed();
        User::query()->where('code', 'KN')->update(['email' => 'kiki@sekolah.test']);

        $this->seed();

        $this->assertSame(34, User::query()->whereNotNull('code')->count());
        $this->assertSame(40, DailyRoutine::query()->count());
        $this->assertSame('kiki@sekolah.test', User::query()->where('code', 'KN')->value('email'));
    }
}
