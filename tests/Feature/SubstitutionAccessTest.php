<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Pages\SubstitutionSelf;
use App\Filament\Resources\Substitutions\Pages\ManageSubstitutions;
use App\Filament\Resources\Substitutions\SubstitutionResource;
use App\Models\Schedule;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SubstitutionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_substitution_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(SubstitutionResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_substitutions_but_not_create_them(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(SubstitutionResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', Substitution::class));
    }

    public function test_a_plain_teacher_cannot_open_the_substitution_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(SubstitutionResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_record_a_substitution_on_behalf_of_a_teacher(): void
    {
        $originalTeacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $originalTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageSubstitutions::class)
            ->callAction('create', data: [
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'substitute_teacher_id' => $substitute->id,
                'reason' => 'Sakit',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('substitutions', [
            'schedule_id' => $schedule->id,
            'substitute_teacher_id' => $substitute->id,
        ]);

        $this->assertSame(1, $substitute->fresh()->notifications()->count());
    }

    public function test_any_non_principal_teacher_can_open_penggantian_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(SubstitutionSelf::getUrl())
            ->assertOk();
    }

    public function test_the_principal_sees_a_message_instead_of_the_form(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(SubstitutionSelf::class)
            ->assertSee('Kepala Sekolah tidak mengisi penggantian');
    }

    public function test_a_teacher_can_submit_a_substitution_for_their_own_schedule(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs($teacher);

        Livewire::test(SubstitutionSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'substitute_teacher_id' => $substitute->id,
                'reason' => 'Ada urusan keluarga',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('substitutions', [
            'schedule_id' => $schedule->id,
            'substitute_teacher_id' => $substitute->id,
            'created_by' => $teacher->id,
        ]);
    }

    public function test_a_teacher_cannot_submit_a_substitution_for_someone_elses_schedule(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $otherTeacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(SubstitutionSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'substitute_teacher_id' => $substitute->id,
                'reason' => 'Mencoba mengganti jadwal orang lain',
            ])
            ->call('save')
            ->assertHasFormErrors(['schedule_id']);
    }

    public function test_the_same_schedule_cannot_be_substituted_twice_on_the_same_date(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 07:00:00'));

        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Substitution::factory()->create([
            'schedule_id' => $schedule->id,
            'date' => '2026-09-14',
        ]);

        $this->actingAs($teacher);

        Livewire::test(SubstitutionSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'substitute_teacher_id' => User::factory()->create()->id,
                'reason' => 'Percobaan kedua',
            ])
            ->call('save')
            ->assertHasFormErrors(['schedule_id' => 'unique']);
    }
}
