<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Term;
use App\Filament\Resources\Journals\Pages\ManageJournals;
use App\Filament\Resources\MonitoringSchedules\Pages\ManageMonitoringSchedules;
use App\Models\Semester;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_authorized_user_can_download_the_teacher_attendance_report(): void
    {
        $administrator = User::factory()->withRoles(Role::SuperAdmin)->create();
        TeacherAttendance::factory()->create(['date' => '2026-10-05']);

        $response = $this->actingAs($administrator)->get(route('reports.attendances.pdf', ['month' => '2026-10']));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('rekap-absensi-2026-10.pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_teacher_cannot_download_the_school_wide_attendance_report(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get(route('reports.attendances.pdf', ['month' => '2026-10']))
            ->assertForbidden();
    }

    public function test_an_authorized_user_can_download_the_remaining_school_reports(): void
    {
        $administrator = User::factory()->withRoles(Role::SuperAdmin)->create();

        foreach ([
            route('reports.journals.pdf', ['date' => '2026-10-05']),
            route('reports.substitutions.pdf', ['date' => '2026-10-05']),
            route('reports.monitoring.pdf', ['month' => '2026-10']),
            route('reports.monev.pdf', ['month' => '2026-10']),
        ] as $url) {
            $this->actingAs($administrator)
                ->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_a_report_can_be_downloaded_for_a_chosen_semester(): void
    {
        $semester = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19');

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        foreach ([
            route('reports.journals.pdf', ['semester' => $semester->id, 'date' => '2026-10-05']),
            route('reports.monitoring.pdf', ['semester' => $semester->id, 'month' => '2026-10']),
            route('reports.attendances.pdf', ['semester' => $semester->id, 'month' => '2026-12']),
        ] as $url) {
            $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_a_period_outside_the_chosen_semester_is_refused(): void
    {
        $semester = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19');

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        $this->get(route('reports.journals.pdf', ['semester' => $semester->id, 'date' => '2027-02-01']))
            ->assertSessionHasErrors('date');
        $this->get(route('reports.monitoring.pdf', ['semester' => $semester->id, 'month' => '2027-02']))
            ->assertSessionHasErrors('month');
        $this->get(route('reports.monitoring.pdf', ['semester' => 9999, 'month' => '2026-10']))
            ->assertSessionHasErrors('semester');
    }

    public function test_a_semester_without_an_end_date_runs_until_the_next_one_starts(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $earlier = $this->semester('2025/2026', Term::Genap, '2026-01-05', null);
        $current = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', null, active: true);

        $this->assertSame('2026-07-12', $earlier->effectiveEndsOn()->toDateString());
        $this->assertSame('2026-10-31', $current->effectiveEndsOn()->toDateString());

        $this->assertTrue(Semester::containing(Carbon::parse('2026-07-12'))->is($earlier));
        $this->assertTrue(Semester::containing(Carbon::parse('2026-07-13'))->is($current));
        $this->assertTrue(Semester::containing(Carbon::parse('2026-10-31'))->is($current));
        $this->assertNull(Semester::containing(Carbon::parse('2025-12-31')));
        $this->assertNull(Semester::containing(Carbon::parse('2026-11-01')));
    }

    public function test_the_semester_the_school_actually_has_works_for_the_download_form_and_the_report(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $current = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', null, active: true);

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageJournals::class)
            ->mountAction('downloadPdf')
            ->assertSchemaStateSet(['semester_id' => $current->id, 'date' => '2026-10-26']);

        Livewire::test(ManageMonitoringSchedules::class)
            ->callAction('downloadPdf', data: ['semester_id' => $current->id, 'month' => '2026-09'])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('reports.monitoring.pdf', ['semester' => $current->id, 'month' => '2026-09']));

        Livewire::test(ManageMonitoringSchedules::class)
            ->callAction('downloadPdf', data: ['semester_id' => $current->id, 'month' => '2026-06'])
            ->assertHasActionErrors(['month']);

        $this->get(route('reports.monitoring.pdf', ['semester' => $current->id, 'month' => '2026-09']))->assertOk();
        $this->get(route('reports.monitoring.pdf', ['semester' => $current->id, 'month' => '2026-06']))->assertSessionHasErrors('month');
    }

    public function test_a_semester_without_dates_cannot_be_chosen_for_a_report(): void
    {
        $semester = Semester::factory()->create();

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        $this->get(route('reports.journals.pdf', ['semester' => $semester->id, 'date' => '2026-10-05']))
            ->assertSessionHasErrors('date');
    }

    public function test_the_report_header_names_the_semester_the_period_falls_in(): void
    {
        $semester = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19');
        $data = ['journals' => collect(), 'date' => Carbon::parse('2026-10-05')];

        $this->assertStringContainsString('Semester 2026/2027 Ganjil', view('reports.journals', $data + ['semester' => $semester])->render());
        $this->assertStringNotContainsString('Semester', view('reports.journals', $data + ['semester' => null])->render());
    }

    public function test_the_download_form_opens_on_the_semester_and_day_being_worked_on(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $current = $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19', active: true);
        $this->semester('2025/2026', Term::Genap, '2026-01-05', '2026-06-27');

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageJournals::class)
            ->mountAction('downloadPdf')
            ->assertSchemaStateSet(['semester_id' => $current->id, 'date' => '2026-10-26']);
    }

    public function test_submitting_the_download_form_goes_to_the_report_for_that_semester(): void
    {
        $older = $this->semester('2025/2026', Term::Genap, '2026-01-05', '2026-06-27');
        $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19', active: true);

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageJournals::class)
            ->callAction('downloadPdf', data: ['semester_id' => $older->id, 'date' => '2026-03-10'])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('reports.journals.pdf', ['semester' => $older->id, 'date' => '2026-03-10']));

        Livewire::test(ManageJournals::class)
            ->callAction('downloadPdf', data: ['semester_id' => $older->id, 'date' => '2026-10-10'])
            ->assertHasActionErrors(['date']);
    }

    public function test_the_month_form_opens_on_the_current_month_of_the_active_semester(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        $this->semester('2026/2027', Term::Ganjil, '2026-07-13', '2026-12-19', active: true);

        $this->actingAs(User::factory()->withRoles(Role::SuperAdmin)->create());

        Livewire::test(ManageMonitoringSchedules::class)
            ->mountAction('downloadPdf')
            ->assertSchemaStateSet(['month' => '2026-10']);
    }

    private function semester(string $year, Term $term, string $startsOn, ?string $endsOn, bool $active = false): Semester
    {
        return Semester::factory()->create([
            'academic_year' => $year,
            'term' => $term,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => $active,
        ]);
    }
}
