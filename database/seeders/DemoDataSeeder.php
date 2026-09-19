<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\ClassMonitoringItemStatus;
use App\Enums\CocurricularActivityType;
use App\Enums\DutyType;
use App\Enums\ExtracurricularAttendanceStatus;
use App\Enums\GraduateDimension;
use App\Enums\OutingStatus;
use App\Enums\SchoolDay;
use App\Models\AdditionalDuty;
use App\Models\Assessment;
use App\Models\CalendarDay;
use App\Models\Classroom;
use App\Models\Cocurricular;
use App\Models\CocurricularSchedule;
use App\Models\Curriculum;
use App\Models\DailyRoutine;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAnnouncement;
use App\Models\ExtracurricularAnnouncementComment;
use App\Models\ExtracurricularAttendance;
use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use App\Models\OutingClass;
use App\Models\PidReservation;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\Supervision;
use App\Models\Task;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Populates every transactional table with realistic sample data for a client
 * presentation, on top of the master data (teachers, classrooms, subjects, time slots,
 * daily routines, semester) already seeded by {@see DatabaseSeeder}.
 *
 * Deliberately NOT wired into DatabaseSeeder: run it by hand, after the master seed,
 * with `php artisan db:seed --class=DemoDataSeeder`. The whole run happens inside one
 * transaction, so a failed run can simply be retried without cleanup.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Teaching time slots per school day, i.e. every {@see TimeSlot} id not
     * already claimed by that day's {@see DailyRoutine} rows.
     *
     * @var array<int, list<int>>
     */
    private const TEACHING_SLOTS = [
        1 => [4, 5, 6, 7, 9, 10, 12, 13, 16, 17, 18, 19],
        2 => [4, 5, 6, 7, 9, 10, 12, 13, 16, 17, 18],
        3 => [4, 5, 6, 7, 9, 10, 12, 13, 16, 17, 18],
        4 => [4, 5, 6, 7, 9, 10, 12, 13, 16, 17, 18],
        5 => [6, 7, 9, 10, 12, 13, 16, 17, 18, 19],
    ];

    private const PRAMUKA_DAY = 5;

    private const PRAMUKA_SLOT = 6;

    private const HAFALAN_DAY = 1;

    private const HAFALAN_SLOT = 19;

    /**
     * Subjects that count toward teaching load, rotated across a classroom's week.
     * PRAMUKA (18) and Penguatan Hafalan (17) are placed on one fixed weekly slot
     * instead (see {@see self::seedSchedules()}), since neither counts toward load.
     *
     * @var list<int>
     */
    private const ROTATING_SUBJECT_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

    /**
     * Plausible Indonesian lesson topics per subject id, cycled through for Journal
     * topics and Assessment chapter names.
     *
     * @var array<int, list<string>>
     */
    private const TOPICS = [
        1 => ['Operasi Hitung Pecahan', 'Bangun Datar dan Sifatnya', 'Pengukuran Panjang dan Berat', 'Statistika Dasar'],
        2 => ['Menggambar Ekspresi', 'Kolase dari Bahan Alam', 'Mewarnai Motif Nusantara', 'Membuat Mozaik'],
        3 => ['Tari Daerah Kalimantan Tengah', 'Gerak Dasar Tari', 'Tari Kreasi Baru', 'Properti dan Kostum Tari'],
        4 => ['Mengenal Alat Musik Ritmis', 'Bernyanyi Lagu Daerah', 'Not Angka Sederhana', 'Ansambel Musik Sekolah'],
        5 => ['Pantomim Sederhana', 'Bermain Peran', 'Naskah Drama Pendek', 'Ekspresi Wajah dan Gerak Tubuh'],
        6 => ['Teks Deskripsi', 'Membaca Pemahaman', 'Menulis Cerita Pendek', 'Puisi dan Pantun'],
        7 => ['Nilai-Nilai Pancasila', 'Hak dan Kewajiban Warga', 'Norma dalam Kehidupan Sehari-hari', 'Keberagaman Budaya Indonesia'],
        8 => ['Mufrodat Sehari-hari', 'Hiwar Sederhana', 'Menulis Huruf Hijaiyah', 'Percakapan di Kelas'],
        9 => ['Greetings and Introduction', 'My Family', 'Numbers and Colors', 'Simple Present Tense'],
        10 => ['Siklus Air', 'Ekosistem di Sekitar Kita', 'Energi dan Perubahannya', 'Peta dan Lingkungan'],
        11 => ['Permainan Bola Kecil', 'Senam Irama', 'Atletik Dasar', 'Permainan Bola Besar'],
        12 => ['Mengenal Potensi Diri', 'Etika Pergaulan', 'Cita-Cita Masa Depan', 'Manajemen Waktu Belajar'],
        13 => ['Kisah Nabi dan Rasul', 'Akhlak Terpuji', 'Tata Cara Wudhu dan Sholat', 'Adab Menuntut Ilmu'],
        14 => ['Pengenalan Perangkat Komputer', 'Dasar Pemrograman Blok', 'Etika Digital', 'Mengolah Dokumen Sederhana'],
        15 => ['Latihan Soal TKA', 'Strategi Mengerjakan Soal', 'Pembahasan Try Out', 'Penguatan Materi Ujian'],
        16 => ["Tahsin Bacaan Al-Qur'an", 'Hafalan Surat Pendek', 'Tajwid Dasar', 'Murojaah Hafalan'],
        17 => ['Murojaah Juz 30', 'Setoran Hafalan Baru', 'Penguatan Hafalan Surat Pilihan'],
        18 => ['Baris Berbaris', 'Tali Temali', 'Sandi Pramuka', 'Permainan Kepramukaan'],
    ];

    /**
     * @var list<array{present: int, permitted: int, sick: int, absent: int}>
     */
    private const CLASS_ATTENDANCE_VARIANTS = [
        ['present' => 32, 'permitted' => 0, 'sick' => 0, 'absent' => 0],
        ['present' => 30, 'permitted' => 1, 'sick' => 1, 'absent' => 0],
        ['present' => 29, 'permitted' => 0, 'sick' => 2, 'absent' => 1],
        ['present' => 31, 'permitted' => 1, 'sick' => 0, 'absent' => 0],
        ['present' => 28, 'permitted' => 2, 'sick' => 1, 'absent' => 1],
    ];

    /**
     * @var list<string>
     */
    private const JOURNAL_REMARKS = [
        'Seluruh peserta didik mengikuti pembelajaran dengan antusias.',
        'Pembelajaran berjalan lancar sesuai rencana.',
        'Peserta didik aktif berdiskusi dan mengerjakan latihan soal.',
        'Kegiatan pembelajaran ditutup dengan refleksi bersama.',
    ];

    private int $semesterId;

    private Carbon $semesterStart;

    private Carbon $today;

    /** @var array<string, int> staff user id by SiPeka code, e.g. 'KN' => Waka Kurikulum */
    private array $staff = [];

    /** @var list<int> */
    private array $teacherIds = [];

    /** @var Collection<int, Classroom> */
    private Collection $classrooms;

    /** @var array<int, list<string>> up to 4 most recent Efektif occurrences (Y-m-d) of each SchoolDay value */
    private array $recentTeachingDates = [];

    /** @var list<string> Y-m-d dates marked Libur */
    private array $liburDates = [];

    /** @var list<string> the 10 most recent Y-m-d dates that required attendance */
    private array $recentAttendanceDates = [];

    public function run(): void
    {
        $semester = Semester::current();

        $this->semesterId = $semester->id;
        $this->semesterStart = $semester->starts_on->copy();
        $this->today = Carbon::today();
        $this->staff = User::query()->whereNotNull('code')->pluck('id', 'code')->all();
        $this->teacherIds = array_values($this->staff);
        $this->classrooms = Classroom::query()->orderBy('id')->get();

        DB::transaction(function (): void {
            $exceptions = $this->seedCalendarDays();

            $exceptionDates = array_column($exceptions, 'date');
            $this->liburDates = array_column(
                array_filter($exceptions, fn (array $exception): bool => $exception['status'] === CalendarDayStatus::Libur),
                'date'
            );

            $this->recentTeachingDates = $this->recentOccurrencesByDay($exceptionDates, 4);
            $this->recentAttendanceDates = $this->computeRecentAttendanceDates();

            $schedules = $this->seedSchedules();
            $this->seedJournals($schedules);
            $this->seedTeacherAttendances();
            $this->seedSubstitutions($schedules);
            $this->seedAssessments($schedules);
            $this->seedPidReservations();
            $this->seedOutingClasses();
            $this->seedSupervisions($schedules);
            $this->seedMonitoring();
            $this->seedTasks();
            $this->seedAdditionalDuties();
            $this->seedCocurricular();
            $this->seedCurricula();
            $this->seedExtracurriculars();
        });
    }

    /**
     * Mark a handful of exception dates on the calendar: national holidays (Libur), a
     * pre-independence-day school event (Kegiatan), and a mid-semester assessment window
     * (KegiatanKhusus). Ordinary school days are left unmarked — Efektif is the implicit
     * default and is never stored (see {@see CalendarDayStatus}).
     *
     * @return list<array{date: string, status: CalendarDayStatus, notes: string}>
     */
    private function seedCalendarDays(): array
    {
        $exceptions = [
            ['date' => '2026-08-10', 'status' => CalendarDayStatus::Kegiatan, 'notes' => 'Lomba Peringatan HUT RI ke-81'],
            ['date' => '2026-08-12', 'status' => CalendarDayStatus::KegiatanKhusus, 'notes' => 'Penilaian Tengah Semester Ganjil'],
            ['date' => '2026-08-13', 'status' => CalendarDayStatus::KegiatanKhusus, 'notes' => 'Penilaian Tengah Semester Ganjil'],
            ['date' => '2026-08-17', 'status' => CalendarDayStatus::Libur, 'notes' => 'Hari Kemerdekaan Republik Indonesia'],
            ['date' => '2026-08-18', 'status' => CalendarDayStatus::Libur, 'notes' => 'Cuti Bersama'],
        ];

        foreach ($exceptions as $exception) {
            CalendarDay::create([
                'semester_id' => $this->semesterId,
                'date' => $exception['date'],
                'status' => $exception['status'],
                'notes' => $exception['notes'],
                'created_by' => $this->staff['KN'],
            ]);
        }

        return $exceptions;
    }

    /**
     * For each school day, the `$count` most recent past occurrences within the semester
     * to date, oldest first, skipping any date in `$excludedDates`.
     *
     * @param  list<string>  $excludedDates
     * @return array<int, list<string>>
     */
    private function recentOccurrencesByDay(array $excludedDates, int $count): array
    {
        $byDay = [];

        foreach (SchoolDay::cases() as $day) {
            $dates = [];
            $cursor = $this->today->copy();

            while ($cursor->greaterThanOrEqualTo($this->semesterStart) && count($dates) < $count) {
                if ($cursor->dayOfWeekIso === $day->value && ! in_array($cursor->toDateString(), $excludedDates, true)) {
                    $dates[] = $cursor->toDateString();
                }

                $cursor->subDay();
            }

            $byDay[$day->value] = array_reverse($dates);
        }

        return $byDay;
    }

    /**
     * The 10 most recent weekdays within the semester to date that required attendance
     * (everything except Libur), oldest first.
     *
     * @return list<string>
     */
    private function computeRecentAttendanceDates(): array
    {
        $dates = [];
        $cursor = $this->today->copy();

        while ($cursor->greaterThanOrEqualTo($this->semesterStart) && count($dates) < 10) {
            if (SchoolDay::tryFrom($cursor->dayOfWeekIso) !== null && ! in_array($cursor->toDateString(), $this->liburDates, true)) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->subDay();
        }

        return array_reverse($dates);
    }

    /**
     * Generate every classroom's weekly timetable: each classroom uses only its own
     * homeroom and assistant teacher (alternating by slot parity), which guarantees zero
     * teacher/classroom conflicts by construction — no clash checking needed.
     *
     * @return Collection<int, Schedule>
     */
    private function seedSchedules(): Collection
    {
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');

        foreach ($this->classrooms as $classroom) {
            $rotationOffset = ($classroom->id - 1) * 3;
            $subjectCounter = 0;

            foreach (self::TEACHING_SLOTS as $day => $slotIds) {
                foreach ($slotIds as $index => $timeSlotId) {
                    $teacherId = $index % 2 === 0 ? $classroom->homeroom_teacher_id : $classroom->assistant_teacher_id;

                    $subjectId = match (true) {
                        $day === self::PRAMUKA_DAY && $timeSlotId === self::PRAMUKA_SLOT => 18,
                        $day === self::HAFALAN_DAY && $timeSlotId === self::HAFALAN_SLOT => 17,
                        default => self::ROTATING_SUBJECT_IDS[($rotationOffset + $subjectCounter) % count(self::ROTATING_SUBJECT_IDS)],
                    };

                    if (! in_array($subjectId, [17, 18], true)) {
                        $subjectCounter++;
                    }

                    $rows[] = [
                        'semester_id' => $this->semesterId,
                        'day_of_week' => $day,
                        'time_slot_id' => $timeSlotId,
                        'classroom_id' => $classroom->id,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacherId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('schedules')->insert($chunk);
        }

        return Schedule::query()->where('semester_id', $this->semesterId)->orderBy('id')->get();
    }

    /**
     * Journal the last 3-4 occurrences of every Schedule slot's weekday, so the last few
     * weeks of Jurnal Guru look fully populated.
     *
     * @param  Collection<int, Schedule>  $schedules
     */
    private function seedJournals(Collection $schedules): void
    {
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');

        foreach ($schedules as $schedule) {
            $dates = $this->recentTeachingDates[$schedule->day_of_week->value] ?? [];
            $topics = self::TOPICS[$schedule->subject_id];

            foreach ($dates as $index => $date) {
                $variant = self::CLASS_ATTENDANCE_VARIANTS[($schedule->id + $index) % count(self::CLASS_ATTENDANCE_VARIANTS)];

                $rows[] = [
                    'date' => $date,
                    'schedule_id' => $schedule->id,
                    'teacher_id' => $schedule->teacher_id,
                    'topic' => $topics[$index % count($topics)],
                    'pages' => null,
                    'resource_url' => null,
                    'description' => self::JOURNAL_REMARKS[($schedule->id + $index) % count(self::JOURNAL_REMARKS)],
                    'students_present' => $variant['present'],
                    'students_permitted' => $variant['permitted'],
                    'students_sick' => $variant['sick'],
                    'students_absent' => $variant['absent'],
                    'created_by' => $schedule->teacher_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('journals')->insert($chunk);
        }
    }

    /**
     * Fill attendance for every teacher across the last 10 school days: mostly Hadir,
     * a scattering of Izin/Sakit, and two teachers on a short Cuti during the window.
     */
    private function seedTeacherAttendances(): void
    {
        $dates = $this->recentAttendanceDates;
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');

        $cutiRanges = count($dates) >= 7 ? [
            $this->staff['HH'] => [$dates[count($dates) - 4], $dates[count($dates) - 3]],
            $this->staff['RJ'] => [$dates[count($dates) - 7], $dates[count($dates) - 6]],
        ] : [];

        foreach ($dates as $date) {
            foreach ($this->teacherIds as $teacherId) {
                $cutiRange = $cutiRanges[$teacherId] ?? null;

                if ($cutiRange !== null && $date >= $cutiRange[0] && $date <= $cutiRange[1]) {
                    $rows[] = [
                        'teacher_id' => $teacherId,
                        'date' => $date,
                        'status' => AttendanceStatus::Cuti->value,
                        'leave_starts_on' => $cutiRange[0],
                        'leave_ends_on' => $cutiRange[1],
                        'notes' => null,
                        'filled_at' => "{$date} 07:00:00",
                        'created_by' => $teacherId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    continue;
                }

                $status = AttendanceStatus::Hadir;
                $notes = null;

                if (fake()->boolean(5)) {
                    $status = fake()->randomElement([AttendanceStatus::Izin, AttendanceStatus::Sakit]);
                    $notes = $status === AttendanceStatus::Izin ? 'Izin acara keluarga' : 'Kurang sehat';
                }

                $rows[] = [
                    'teacher_id' => $teacherId,
                    'date' => $date,
                    'status' => $status->value,
                    'leave_starts_on' => null,
                    'leave_ends_on' => null,
                    'notes' => $notes,
                    'filled_at' => "{$date} 07:00:00",
                    'created_by' => $teacherId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('teacher_attendances')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function seedSubstitutions(Collection $schedules): void
    {
        $reasons = ['Sakit', 'Ada urusan keluarga', 'Dinas luar', 'Mengikuti pelatihan'];
        $usedDates = [];

        foreach ($schedules->shuffle()->take(8) as $schedule) {
            $dayDates = $this->recentTeachingDates[$schedule->day_of_week->value] ?? [];

            if ($dayDates === []) {
                continue;
            }

            $date = collect($dayDates)->reverse()->first(fn (string $d): bool => ! in_array($d, $usedDates, true)) ?? end($dayDates);
            $usedDates[] = $date;

            Substitution::create([
                'date' => $date,
                'schedule_id' => $schedule->id,
                'substitute_teacher_id' => $this->randomTeacherExcept($schedule->teacher_id),
                'reason' => fake()->randomElement($reasons),
                'created_by' => $this->staff['KN'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function seedAssessments(Collection $schedules): void
    {
        $mondayDates = $this->recentTeachingDates[SchoolDay::Senin->value] ?? [];
        $date = $mondayDates === [] ? $this->today->toDateString() : end($mondayDates);

        foreach ($this->classrooms as $classroom) {
            $schedule = $schedules->first(fn (Schedule $s): bool => $s->classroom_id === $classroom->id
                && $s->day_of_week === SchoolDay::Senin
                && $s->time_slot_id === 4);

            if ($schedule === null) {
                continue;
            }

            $topics = self::TOPICS[$schedule->subject_id];
            $chapterNumber = (($classroom->id - 1) % 4) + 1;
            $hasLinks = $classroom->id % 2 === 0;

            Assessment::create([
                'classroom_id' => $classroom->id,
                'subject_id' => $schedule->subject_id,
                'teacher_id' => $schedule->teacher_id,
                'date' => $date,
                'chapter' => "Bab {$chapterNumber} - {$topics[0]}",
                'is_completed' => ! in_array($classroom->id, [7, 14], true),
                'question_link' => $hasLinks ? 'https://forms.gle/'.Str::random(11) : null,
                'result_link' => $hasLinks ? 'https://docs.google.com/spreadsheets/d/'.Str::random(30).'/edit?usp=sharing' : null,
            ]);
        }
    }

    private function seedPidReservations(): void
    {
        $rows = [
            ['days' => -10, 'status' => OutingStatus::Disetujui],
            ['days' => -7, 'status' => OutingStatus::Disetujui],
            ['days' => -5, 'status' => OutingStatus::Disetujui],
            ['days' => -3, 'status' => OutingStatus::Disetujui],
            ['days' => 2, 'status' => OutingStatus::Disetujui],
            ['days' => 5, 'status' => OutingStatus::Menunggu],
            ['days' => 9, 'status' => OutingStatus::Menunggu],
            ['days' => 13, 'status' => OutingStatus::Menunggu],
        ];

        $locations = ['Ruang PID 1', 'Ruang PID 2'];
        $times = [['08:00', '09:00'], ['09:30', '10:30'], ['13:00', '14:00']];
        $requesterCodes = ['AA', 'PW', 'NK', 'FB', 'NH', 'DP', 'RK', 'MR'];

        foreach ($rows as $index => $row) {
            $subjectId = self::ROTATING_SUBJECT_IDS[$index % count(self::ROTATING_SUBJECT_IDS)];
            $topics = self::TOPICS[$subjectId];
            [$start, $end] = $times[$index % count($times)];

            PidReservation::create([
                'date' => $this->today->copy()->addDays($row['days'])->toDateString(),
                'starts_at' => $start,
                'ends_at' => $end,
                'location' => $locations[$index % count($locations)],
                'subject_id' => $subjectId,
                'purpose' => "Pemutaran media pembelajaran: {$topics[0]}",
                'status' => $row['status'],
                'requested_by' => $this->staff[$requesterCodes[$index % count($requesterCodes)]],
            ]);
        }
    }

    private function seedOutingClasses(): void
    {
        $rows = [
            ['classroom_id' => 1, 'days' => -14, 'status' => OutingStatus::Disetujui, 'destination' => 'Museum Kalimantan Tengah'],
            ['classroom_id' => 3, 'days' => -10, 'status' => OutingStatus::Disetujui, 'destination' => 'Taman Pintar'],
            ['classroom_id' => 5, 'days' => -6, 'status' => OutingStatus::Disetujui, 'destination' => 'Kebun Binatang Mini'],
            ['classroom_id' => 7, 'days' => -2, 'status' => OutingStatus::Menunggu, 'destination' => 'Perpustakaan Daerah'],
            ['classroom_id' => 9, 'days' => 4, 'status' => OutingStatus::Menunggu, 'destination' => 'Pemadam Kebakaran Kota'],
            ['classroom_id' => 11, 'days' => 9, 'status' => OutingStatus::Menunggu, 'destination' => 'Kantor Pos Indonesia'],
            ['classroom_id' => 13, 'days' => 15, 'status' => OutingStatus::Disetujui, 'destination' => 'Taman Kota'],
        ];

        foreach ($rows as $row) {
            $classroom = $this->classrooms->firstWhere('id', $row['classroom_id']);

            OutingClass::create([
                'classroom_id' => $classroom->id,
                'date' => $this->today->copy()->addDays($row['days'])->toDateString(),
                'destination' => $row['destination'],
                'description' => 'Kunjungan edukasi untuk memperkaya pengalaman belajar peserta didik di luar kelas.',
                'status' => $row['status'],
                'requested_by' => $classroom->homeroom_teacher_id,
            ]);
        }
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function seedSupervisions(Collection $schedules): void
    {
        $rows = [
            ['index' => 40, 'days' => -12, 'supervisor' => 'JJ'],
            ['index' => 190, 'days' => -8, 'supervisor' => 'KN'],
            ['index' => 340, 'days' => -4, 'supervisor' => 'JJ'],
            ['index' => 490, 'days' => 5, 'supervisor' => 'KN'],
            ['index' => 640, 'days' => 11, 'supervisor' => 'JJ'],
        ];

        foreach ($rows as $row) {
            $schedule = $schedules[$row['index']];
            $classroom = $this->classrooms->firstWhere('id', $schedule->classroom_id);
            $supervisorId = $this->staff[$row['supervisor']];

            Supervision::create([
                'teacher_id' => $schedule->teacher_id,
                'subject_id' => $schedule->subject_id,
                'supervisor_id' => $supervisorId,
                'date' => $this->today->copy()->addDays($row['days'])->toDateString(),
                'location' => "Ruang Kelas {$classroom->code}",
                'created_by' => $supervisorId,
            ]);
        }
    }

    /**
     * 3 monitoring visits: the first scores well, the second is middling, and the third
     * has one Belum Ada item — so not every visit in the demo is a perfect score.
     */
    private function seedMonitoring(): void
    {
        $visits = [
            ['classroom_id' => 2, 'days' => -20],
            ['classroom_id' => 6, 'days' => -14],
            ['classroom_id' => 10, 'days' => -7],
        ];

        foreach ($visits as $visitIndex => $visit) {
            $schedule = MonitoringSchedule::create([
                'date' => $this->today->copy()->addDays($visit['days'])->toDateString(),
                'classroom_id' => $visit['classroom_id'],
                'notes' => 'Kunjungan rutin monitoring administrasi kelas.',
                'created_by' => $this->staff['KN'],
            ]);

            $result = MonitoringResult::startFor($schedule);

            foreach ($result->items()->get() as $itemIndex => $item) {
                $item->update(['status' => $this->monitoringItemStatus($visitIndex, $itemIndex)]);
            }

            $result->computeScore();
        }
    }

    private function monitoringItemStatus(int $visitIndex, int $itemIndex): ClassMonitoringItemStatus
    {
        return match ($visitIndex) {
            0 => $itemIndex < 26 ? ClassMonitoringItemStatus::Lengkap : ClassMonitoringItemStatus::PerluDiperbaiki,
            1 => $itemIndex < 23 ? ClassMonitoringItemStatus::Lengkap : ClassMonitoringItemStatus::PerluDiperbaiki,
            default => match (true) {
                $itemIndex === 27 => ClassMonitoringItemStatus::BelumAda,
                $itemIndex >= 20 => ClassMonitoringItemStatus::PerluDiperbaiki,
                default => ClassMonitoringItemStatus::Lengkap,
            },
        };
    }

    private function seedTasks(): void
    {
        $tasks = [
            ['title' => 'Pengumpulan RPP Semester Ganjil', 'description' => 'Setiap guru mengumpulkan RPP/Modul Ajar untuk seluruh mata pelajaran yang diampu.', 'starts' => -25, 'due' => -12, 'link' => null, 'by' => 'KN'],
            ['title' => 'Update Data Siswa di Dapodik', 'description' => 'Memastikan data peserta didik pada Dapodik sudah sesuai kondisi terbaru semester ini.', 'starts' => -18, 'due' => -5, 'link' => null, 'by' => 'FB'],
            ['title' => 'Pelaporan Nilai Tengah Semester', 'description' => 'Wali kelas menyusun dan mengunggah rekap nilai tengah semester ke sistem.', 'starts' => -3, 'due' => 3, 'link' => null, 'by' => 'KN'],
            ['title' => 'Persiapan Akreditasi Sekolah', 'description' => 'Menyiapkan dokumen dan portofolio kelas untuk mendukung proses akreditasi.', 'starts' => 0, 'due' => 10, 'link' => null, 'by' => 'JW'],
            ['title' => 'Pelatihan Kurikulum Merdeka Lanjutan', 'description' => 'Pendaftaran dan persiapan peserta pelatihan penguatan Kurikulum Merdeka.', 'starts' => 5, 'due' => 20, 'link' => $this->fakeDriveUrl(), 'by' => 'KN'],
        ];

        foreach ($tasks as $task) {
            $model = Task::create([
                'title' => $task['title'],
                'description' => $task['description'],
                'link' => $task['link'],
                'starts_on' => $this->today->copy()->addDays($task['starts'])->toDateString(),
                'due_on' => $this->today->copy()->addDays($task['due'])->toDateString(),
                'created_by' => $this->staff[$task['by']],
            ]);

            $isPastDue = $task['due'] < 0;
            $assignees = collect($this->teacherIds)->shuffle()->take(fake()->numberBetween(2, 5));

            foreach ($assignees as $index => $teacherId) {
                $isCompleted = $isPastDue && $index !== 0;

                $model->assignees()->attach($teacherId, [
                    'is_completed' => $isCompleted,
                    'completed_at' => $isCompleted ? $this->today->copy()->addDays($task['due'] - 1)->toDateString().' 08:00:00' : null,
                ]);
            }
        }
    }

    private function seedAdditionalDuties(): void
    {
        $duties = [
            ['teacher' => 'HH', 'title' => 'Kepala Perpustakaan', 'type' => DutyType::Utama, 'jp' => 3],
            ['teacher' => 'BH', 'title' => 'Bendahara BOS', 'type' => DutyType::Utama, 'jp' => 2],
            ['teacher' => 'NC', 'title' => 'Pembina OSIS', 'type' => DutyType::Tambahan, 'jp' => 2],
            ['teacher' => 'FM', 'title' => 'Koordinator UKS', 'type' => DutyType::Tambahan, 'jp' => 2],
            ['teacher' => 'AT', 'title' => 'Operator Dapodik', 'type' => DutyType::Tambahan, 'jp' => 3],
        ];

        foreach ($duties as $duty) {
            AdditionalDuty::create([
                'teacher_id' => $this->staff[$duty['teacher']],
                'title' => $duty['title'],
                'type' => $duty['type'],
                'jp' => $duty['jp'],
                'created_by' => $this->staff['KN'],
            ]);
        }
    }

    private function seedCocurricular(): void
    {
        $schedules = [
            ['theme' => 'P5 - Kearifan Lokal Kalimantan Tengah', 'days' => -21, 'classrooms' => [1, 2, 3, 4]],
            ['theme' => 'P5 - Gaya Hidup Berkelanjutan', 'days' => -14, 'classrooms' => [5, 6, 7, 8, 9]],
            ['theme' => 'P5 - Bhinneka Tunggal Ika', 'days' => -7, 'classrooms' => [10, 11, 12]],
        ];

        $activityTypes = CocurricularActivityType::cases();
        $dimensions = GraduateDimension::cases();

        foreach ($schedules as $scheduleData) {
            $schedule = CocurricularSchedule::create([
                'date' => $this->today->copy()->addDays($scheduleData['days'])->toDateString(),
                'theme' => $scheduleData['theme'],
                'created_by' => $this->staff['KN'],
            ]);

            $schedule->classrooms()->attach($scheduleData['classrooms']);

            foreach ($scheduleData['classrooms'] as $classroomId) {
                $classroom = $this->classrooms->firstWhere('id', $classroomId);
                $activityType = $activityTypes[$classroomId % count($activityTypes)];

                $pickedDimensions = array_unique([
                    $dimensions[$classroomId % count($dimensions)]->value,
                    $dimensions[($classroomId + 1) % count($dimensions)]->value,
                    $dimensions[($classroomId + 2) % count($dimensions)]->value,
                ]);

                Cocurricular::create([
                    'cocurricular_schedule_id' => $schedule->id,
                    'classroom_id' => $classroomId,
                    'activity_type' => $activityType,
                    'description' => "Peserta didik kelas {$classroom->code} melaksanakan kegiatan bertema \"{$scheduleData['theme']}\" dan mempresentasikan hasilnya.",
                    'dimensions' => array_values($pickedDimensions),
                    'created_by' => $classroom->homeroom_teacher_id,
                ]);
            }
        }
    }

    private function seedCurricula(): void
    {
        $titles = [
            'Modul Ajar Matematika Kelas IV Semester Ganjil',
            'RPP Bahasa Indonesia Kelas II Semester Ganjil',
            'ATP Pendidikan Pancasila Kelas V',
            'Modul Ajar IPAS Kelas III Semester Ganjil',
            'Kurikulum Operasional Satuan Pendidikan 2026/2027',
        ];

        foreach ($titles as $title) {
            Curriculum::create([
                'title' => $title,
                'drive_url' => $this->fakeDriveUrl(),
                'created_by' => $this->staff['FB'],
            ]);
        }
    }

    private function seedExtracurriculars(): void
    {
        $extracurriculars = [
            ['name' => 'Pramuka', 'day' => SchoolDay::Jumat, 'start' => '15:30', 'end' => '17:00', 'location' => 'Lapangan Sekolah', 'coach' => null, 'teachers' => ['TI', 'SW']],
            ['name' => 'Futsal', 'day' => SchoolDay::Selasa, 'start' => '15:00', 'end' => '16:30', 'location' => 'Lapangan Futsal', 'coach' => 'Kak Randi', 'teachers' => ['ML', 'HH']],
            ['name' => "Tahfidz Qur'an", 'day' => SchoolDay::Rabu, 'start' => '15:00', 'end' => '16:00', 'location' => 'Aula', 'coach' => null, 'teachers' => ['BH', 'NC']],
            ['name' => 'Robotik', 'day' => SchoolDay::Kamis, 'start' => '15:00', 'end' => '16:30', 'location' => 'Lab Komputer', 'coach' => 'Kak Dimas (Robotik Indonesia)', 'teachers' => ['FM', 'AT']],
        ];

        foreach ($extracurriculars as $data) {
            $extracurricular = Extracurricular::create([
                'name' => $data['name'],
                'day' => $data['day'],
                'start_time' => $data['start'],
                'end_time' => $data['end'],
                'location' => $data['location'],
                'external_coach' => $data['coach'],
                'created_by' => $this->staff['MK'],
            ]);

            $teacherIds = array_map(fn (string $code): int => $this->staff[$code], $data['teachers']);
            $extracurricular->assistantTeachers()->attach($teacherIds);

            $dates = $this->recentTeachingDates[$data['day']->value] ?? [];

            foreach ($teacherIds as $teacherId) {
                foreach ($dates as $index => $date) {
                    $status = ExtracurricularAttendanceStatus::Hadir;

                    if ($index === 0 && fake()->boolean(20)) {
                        $status = fake()->randomElement([ExtracurricularAttendanceStatus::Izin, ExtracurricularAttendanceStatus::Sakit]);
                    }

                    ExtracurricularAttendance::create([
                        'extracurricular_id' => $extracurricular->id,
                        'teacher_id' => $teacherId,
                        'date' => $date,
                        'status' => $status,
                    ]);
                }
            }
        }

        $this->seedExtracurricularAnnouncements();
    }

    private function seedExtracurricularAnnouncements(): void
    {
        $announcements = [
            ['title' => 'Pendaftaran Ekstrakurikuler Semester Ganjil 2026/2027 Dibuka', 'body' => 'Peserta didik dapat mendaftar ekstrakurikuler pilihan melalui wali kelas masing-masing mulai pekan ini.'],
            ['title' => 'Jadwal Latihan Pramuka Berubah Mulai Pekan Depan', 'body' => 'Latihan Pramuka dipindahkan ke pukul 15.30 - 17.00 WIB mengikuti jadwal sholat Ashar berjamaah.'],
            ['title' => 'Persiapan Lomba Futsal Antar SD se-Kota', 'body' => 'Tim futsal sekolah mulai menjalani latihan intensif untuk persiapan lomba antar SD se-kota bulan depan.'],
        ];

        $commentBodies = [
            'Siap, terima kasih infonya.',
            'Apakah ada biaya pendaftaran?',
            'Ditunggu jadwal lengkapnya.',
            'Alhamdulillah, semoga lancar.',
        ];

        foreach ($announcements as $data) {
            $announcement = ExtracurricularAnnouncement::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'image_drive_url' => null,
                'created_by' => $this->staff['MK'],
            ]);

            $commenters = collect($this->teacherIds)->shuffle()->take(fake()->numberBetween(1, 3));

            foreach ($commenters as $teacherId) {
                ExtracurricularAnnouncementComment::create([
                    'extracurricular_announcement_id' => $announcement->id,
                    'user_id' => $teacherId,
                    'body' => fake()->randomElement($commentBodies),
                ]);
            }
        }
    }

    private function randomTeacherExcept(int ...$excludeIds): int
    {
        return collect($this->teacherIds)->reject(fn (int $id): bool => in_array($id, $excludeIds, true))->random();
    }

    private function fakeDriveUrl(): string
    {
        return 'https://drive.google.com/file/d/'.Str::random(30).'/view?usp=sharing';
    }
}
