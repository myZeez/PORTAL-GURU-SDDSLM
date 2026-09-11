<?php

namespace Tests\Feature;

use App\Enums\CalendarDayStatus;
use App\Models\CalendarDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalendarDayStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_date_with_no_row_is_efektif(): void
    {
        $this->assertSame(CalendarDayStatus::Efektif, CalendarDay::statusFor('2026-09-14'));
    }

    public function test_a_date_with_a_row_uses_its_stored_status(): void
    {
        CalendarDay::factory()->libur()->create(['date' => '2026-09-14']);

        $this->assertSame(CalendarDayStatus::Libur, CalendarDay::statusFor('2026-09-14'));
    }

    public function test_statusfor_accepts_a_carbon_instance(): void
    {
        CalendarDay::factory()->libur()->create(['date' => '2026-09-14']);

        $this->assertSame(CalendarDayStatus::Libur, CalendarDay::statusFor(now()->setDate(2026, 9, 14)));
    }

    public function test_marking_a_date_records_who_marked_it(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $calendarDay = CalendarDay::factory()->create(['created_by' => null]);

        $this->assertSame($admin->id, $calendarDay->created_by);
    }

    /**
     * @return array<string, array{0: CalendarDayStatus, 1: bool, 2: bool, 3: bool, 4: bool}>
     */
    public static function businessRules(): array
    {
        // [status, requiresAttendance, requiresJournal, requiresSimpati, allowsMonitoring]
        return [
            'Efektif' => [CalendarDayStatus::Efektif, true, true, true, true],
            'Kegiatan' => [CalendarDayStatus::Kegiatan, true, false, true, true],
            'Kegiatan Khusus' => [CalendarDayStatus::KegiatanKhusus, true, false, true, true],
            'Non-Efektif' => [CalendarDayStatus::NonEfektif, false, false, true, false],
            'Libur' => [CalendarDayStatus::Libur, false, false, false, false],
        ];
    }

    #[DataProvider('businessRules')]
    public function test_each_status_carries_the_rules_the_school_decided(
        CalendarDayStatus $status,
        bool $requiresAttendance,
        bool $requiresJournal,
        bool $requiresSimpati,
        bool $allowsMonitoring,
    ): void {
        $this->assertSame($requiresAttendance, $status->requiresAttendance());
        $this->assertSame($requiresJournal, $status->requiresJournal());
        $this->assertSame($requiresSimpati, $status->requiresSimpati());
        $this->assertSame($allowsMonitoring, $status->allowsMonitoring());
    }

    public function test_efektif_is_never_offered_as_something_an_admin_marks(): void
    {
        $this->assertNotContains(CalendarDayStatus::Efektif, CalendarDayStatus::exceptions());
        $this->assertCount(4, CalendarDayStatus::exceptions());
    }
}
