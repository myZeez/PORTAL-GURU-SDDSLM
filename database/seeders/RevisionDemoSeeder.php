<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\PerformancePeriod;
use App\Enums\Role;
use App\Filament\Performance\PerformanceCalculator;
use App\Models\Classroom;
use App\Models\Journal;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\PerformanceSnapshot;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\TeacherAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the SIMPATI checklist and the performance dashboard, on top of what
 * {@see DemoDataSeeder} already provides: it tops up attendance and journals so the
 * evaluated months look like a normal school month, then adds SIMPATI ticks, a saved
 * monthly snapshot and a few follow-ups. Run by hand with
 * `php artisan db:seed --class=RevisionDemoSeeder`; safe to run again.
 *
 * Scoring is deliberately left off: weights and cut-offs are for the school to agree on.
 */
class RevisionDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedAttendanceAndJournals();
            $this->seedSimpati();
            $this->seedPerformanceHistory();
            $this->seedFollowUps();
        });
    }

    /**
     * The start of the window the demo data covers: the first day of last month, so both
     * the current month and the previous one have something to evaluate.
     */
    private function windowStart(): Carbon
    {
        return Carbon::today()->subMonthNoOverflow()->startOfMonth();
    }

    /**
     * Fill in attendance and teaching journals for every school day since the start of
     * the window, up to yesterday: mostly complete, with a believable scattering of
     * early leaves, permits, sick days and the odd missing journal. Days that already
     * have a record are left alone, so this only tops up what DemoDataSeeder started.
     */
    private function seedAttendanceAndJournals(): void
    {
        $today = Carbon::today();
        $semester = Semester::current();
        $teachers = User::query()->where('is_active', true)->get()->reject(fn (User $user): bool => $user->isPrincipal());
        $schedules = $semester
            ? Schedule::query()->where('semester_id', $semester->id)->get()->groupBy('day_of_week')
            : collect();

        $attendanceRows = [];
        $journalRows = [];

        for ($date = $this->windowStart(); $date->lt($today); $date->addDay()) {
            $day = $date->toDateString();
            $stamp = $day.' 15:00:00';

            if (TeacherAttendance::isFillableDate($date)) {
                foreach ($teachers as $teacher) {
                    $roll = crc32("attendance|{$teacher->id}|{$day}") % 100;
                    $status = match (true) {
                        $roll < 88 => AttendanceStatus::Hadir,
                        $roll < 92 => AttendanceStatus::PulangCepat,
                        $roll < 96 => AttendanceStatus::Izin,
                        $roll < 99 => AttendanceStatus::Sakit,
                        default => AttendanceStatus::Alpa,
                    };

                    $attendanceRows[] = [
                        'teacher_id' => $teacher->id,
                        'date' => $day,
                        'status' => $status->value,
                        'early_leave_reason' => $status === AttendanceStatus::PulangCepat ? 'Keperluan keluarga mendesak.' : null,
                        'notes' => null,
                        'filled_at' => $day.' 07:15:00',
                        'created_by' => $teacher->id,
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ];
                }
            }

            if (Journal::isFillableDate($date)) {
                foreach ($schedules->get($date->dayOfWeekIso, collect()) as $schedule) {
                    if (crc32("journal|{$schedule->id}|{$day}") % 100 >= 93) {
                        continue;
                    }

                    $journalRows[] = [
                        'date' => $day,
                        'schedule_id' => $schedule->id,
                        'teacher_id' => $schedule->teacher_id,
                        'topic' => 'Materi pembelajaran pekan ini',
                        'students_present' => 27,
                        'students_permitted' => 1,
                        'students_sick' => 0,
                        'students_absent' => 0,
                        'created_by' => $schedule->teacher_id,
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ];
                }
            }
        }

        foreach (array_chunk($attendanceRows, 500) as $chunk) {
            DB::table('teacher_attendances')->insertOrIgnore($chunk);
        }

        foreach (array_chunk($journalRows, 500) as $chunk) {
            DB::table('journals')->insertOrIgnore($chunk);
        }
    }

    /**
     * SIMPATI ticks since the start of the window. Most classes are complete, a few have
     * the odd gap, and a handful have not ticked today yet, so the dashboard has something
     * to show on both sides.
     */
    private function seedSimpati(): void
    {
        $today = Carbon::today();
        $dates = SimpatiCheck::requiredDatesBetween($this->windowStart(), $today);

        foreach (Classroom::query()->whereNotNull('assistant_teacher_id')->orderBy('id')->get() as $classroom) {
            $assistant = User::query()->find($classroom->assistant_teacher_id);

            foreach ($dates as $date) {
                $isToday = $date->isSameDay($today);
                $roll = crc32($classroom->id.'|'.$date->toDateString()) % 100;

                if ($roll >= ($isToday ? 60 : 88)) {
                    continue;
                }

                SimpatiCheck::record($classroom, $date, true, $assistant);
            }
        }
    }

    /**
     * Last month's figures saved as a snapshot, so the history table is not empty.
     */
    private function seedPerformanceHistory(): void
    {
        $waka = User::query()->where('role', Role::WakaKurikulum->value)->first();

        if (! $waka) {
            return;
        }

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $settings = PerformanceSetting::current();
        $results = (new PerformanceCalculator($from, $to, $settings))
            ->calculate(PerformanceCalculator::visibleTeachers($waka));

        foreach ($results as $result) {
            PerformanceSnapshot::query()->updateOrCreate(
                ['teacher_id' => $result['teacher']->id, 'period_start' => $from->toDateString(), 'period_end' => $to->toDateString()],
                [
                    'period_type' => PerformancePeriod::Month,
                    'indicators' => collect($result['indicators'])->map(fn (array $indicator): array => [
                        'value' => $indicator['value'],
                        'counted' => $indicator['counted'],
                        'full' => $indicator['full'],
                    ])->all(),
                    'jp' => $result['jp'],
                    'score' => $result['score']['value'] ?? null,
                    'saved_by' => $waka->id,
                ],
            );
        }
    }

    /**
     * A few findings with recommendations, in different states.
     */
    private function seedFollowUps(): void
    {
        $waka = User::query()->where('role', Role::WakaKurikulum->value)->first();
        $teachers = User::query()->where('is_active', true)->whereNull('role')->orderBy('id')->limit(3)->get();

        if (! $waka || $teachers->count() < 3 || PerformanceFollowUp::query()->exists()) {
            return;
        }

        $items = [
            [PerformanceIndicator::Journal, 'Jurnal mengajar beberapa kali terlambat diisi.', 'Isi jurnal di hari yang sama, paling lambat sebelum pulang.', PerformanceFollowUpStatus::InProgress, 7],
            [PerformanceIndicator::ClassAdministration, 'Checklist administrasi kelas belum lengkap pada kunjungan terakhir.', 'Lengkapi dokumen yang ditandai Belum Ada sebelum kunjungan berikutnya.', PerformanceFollowUpStatus::Open, 14],
            [PerformanceIndicator::Attendance, 'Beberapa hari absensi tidak diisi.', 'Isi absensi sebelum pukul 08.00 setiap hari.', PerformanceFollowUpStatus::Done, -3],
        ];

        foreach ($items as $index => [$indicator, $finding, $recommendation, $status, $days]) {
            $followUp = PerformanceFollowUp::query()->create([
                'teacher_id' => $teachers[$index]->id,
                'indicator' => $indicator,
                'finding' => $finding,
                'recommendation' => $recommendation,
                'responsible_id' => $teachers[$index]->id,
                'target_date' => Carbon::today()->addDays($days)->toDateString(),
                'created_by' => $waka->id,
            ]);

            $followUp->markAs($status);
        }
    }
}
