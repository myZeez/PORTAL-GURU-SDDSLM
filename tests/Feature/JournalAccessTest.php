<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Pages\JournalSelf;
use App\Filament\Resources\Journals\JournalResource;
use App\Filament\Resources\Journals\Pages\ManageJournals;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class JournalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_open_the_journal_resource(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create())
            ->get(JournalResource::getUrl())
            ->assertOk();
    }

    public function test_the_principal_can_read_journals_but_not_create_them(): void
    {
        $principal = User::factory()->withRoles(Role::KepalaSekolah)->create();

        $this->actingAs($principal)
            ->get(JournalResource::getUrl())
            ->assertOk();

        $this->assertFalse($principal->can('create', Journal::class));
    }

    public function test_a_plain_teacher_cannot_open_the_journal_resource(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(JournalResource::getUrl())
            ->assertForbidden();
    }

    public function test_an_administrator_can_record_a_journal_on_behalf_of_a_teacher(): void
    {
        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs(User::factory()->withRoles(Role::AdminKurikulum)->create());

        Livewire::test(ManageJournals::class)
            ->callAction('create', data: [
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'teacher_id' => $teacher->id,
                'topic' => 'Penjumlahan pecahan',
                'students_present' => 28,
                'students_permitted' => 0,
                'students_sick' => 0,
                'students_absent' => 0,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('journals', [
            'schedule_id' => $schedule->id,
            'teacher_id' => $teacher->id,
            'topic' => 'Penjumlahan pecahan',
        ]);
    }

    public function test_any_non_principal_teacher_can_open_jurnal_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(JournalSelf::getUrl())
            ->assertOk();
    }

    public function test_the_principal_sees_a_message_instead_of_the_form(): void
    {
        $this->actingAs(User::factory()->withRoles(Role::KepalaSekolah)->create());

        Livewire::test(JournalSelf::class)
            ->assertSee('Kepala Sekolah tidak mengisi jurnal mengajar');
    }

    public function test_a_teacher_can_fill_their_own_journal_for_todays_slot(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs($teacher);

        Livewire::test(JournalSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'topic' => 'Penjumlahan pecahan',
                'students_present' => 28,
                'students_permitted' => 1,
                'students_sick' => 0,
                'students_absent' => 0,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('journals', [
            'schedule_id' => $schedule->id,
            'teacher_id' => $teacher->id,
            'topic' => 'Penjumlahan pecahan',
            'students_permitted' => 1,
        ]);
    }

    public function test_a_teacher_cannot_journal_a_slot_that_is_not_theirs(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $otherTeacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(JournalSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'topic' => 'Mencoba mengisi jurnal orang lain',
            ])
            ->call('save')
            ->assertHasFormErrors(['schedule_id']);
    }

    public function test_a_substitute_can_journal_the_slot_they_covered(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $originalTeacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $originalTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Substitution::factory()->create([
            'schedule_id' => $schedule->id,
            'date' => '2026-09-14',
            'substitute_teacher_id' => $substitute->id,
        ]);

        $this->actingAs($substitute);

        Livewire::test(JournalSelf::class)
            ->fillForm([
                'date' => '2026-09-14',
                'schedule_id' => $schedule->id,
                'topic' => 'Materi pengganti',
                'students_present' => 27,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('journals', [
            'schedule_id' => $schedule->id,
            'teacher_id' => $substitute->id,
            'topic' => 'Materi pengganti',
        ]);
    }

    public function test_a_teacher_can_edit_their_own_journal_entry(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        $journal = Journal::factory()->create([
            'schedule_id' => $schedule->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-14',
            'topic' => 'Materi awal',
        ]);

        $this->actingAs($teacher);

        Livewire::test(JournalSelf::class)
            ->call('edit', $journal->id)
            ->assertSet('editingId', $journal->id)
            ->fillForm(['topic' => 'Materi sudah direvisi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Materi sudah direvisi', $journal->refresh()->topic);
    }

    public function test_a_teacher_cannot_edit_someone_elses_journal_entry(): void
    {
        $owner = User::factory()->create();
        $schedule = Schedule::factory()->create(['teacher_id' => $owner->id]);
        $journal = Journal::factory()->create([
            'schedule_id' => $schedule->id,
            'teacher_id' => $owner->id,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(JournalSelf::class)
            ->call('edit', $journal->id)
            ->assertSet('editingId', null);
    }

    public function test_resubmitting_the_same_slot_updates_instead_of_duplicating(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 09:00:00'));

        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $this->actingAs($teacher);

        Livewire::test(JournalSelf::class)
            ->fillForm(['date' => '2026-09-14', 'schedule_id' => $schedule->id, 'topic' => 'Versi pertama'])
            ->call('save');

        Livewire::test(JournalSelf::class)
            ->fillForm(['date' => '2026-09-14', 'schedule_id' => $schedule->id, 'topic' => 'Versi kedua'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Journal::query()->where('schedule_id', $schedule->id)->count());
        $this->assertDatabaseHas('journals', ['schedule_id' => $schedule->id, 'topic' => 'Versi kedua']);
    }
}
