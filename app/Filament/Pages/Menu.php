<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AdditionalDuties\AdditionalDutyResource;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\CalendarDays\CalendarDayResource;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Cocurriculars\CocurricularResource;
use App\Filament\Resources\CocurricularSchedules\CocurricularScheduleResource;
use App\Filament\Resources\Curricula\CurriculumResource;
use App\Filament\Resources\DailyRoutines\DailyRoutineResource;
use App\Filament\Resources\ExtracurricularAttendances\ExtracurricularAttendanceResource;
use App\Filament\Resources\Extracurriculars\ExtracurricularResource;
use App\Filament\Resources\Journals\JournalResource;
use App\Filament\Resources\OutingClasses\OutingClassResource;
use App\Filament\Resources\PidReservations\PidReservationResource;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Substitutions\SubstitutionResource;
use App\Filament\Resources\Supervisions\SupervisionResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use App\Filament\Resources\TimeSlots\TimeSlotResource;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * A roadmap of every Portal Guru module. Live modules link straight to their page;
 * modules still being built land here too, so the mobile dock never dead-ends.
 */
class Menu extends Page
{
    protected string $view = 'filament.pages.menu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Menu Lainnya';

    protected static ?string $title = 'Menu Lainnya';

    protected static ?int $navigationSort = 100;

    /**
     * Every module in the project, grouped by build phase.
     *
     * @return array<int, array{phase: string, status: string, modules: list<array{label: string, icon: Heroicon, url: ?string}>}>
     */
    public function getModuleGroups(): array
    {
        return [
            [
                'phase' => 'Fase 1 — Fondasi',
                'status' => 'Selesai',
                'modules' => [
                    ['label' => 'Guru & Staf', 'icon' => Heroicon::OutlinedUsers, 'url' => UserResource::getUrl()],
                    ['label' => 'Rombel', 'icon' => Heroicon::OutlinedAcademicCap, 'url' => ClassroomResource::getUrl()],
                    ['label' => 'Mata Pelajaran', 'icon' => Heroicon::OutlinedBookOpen, 'url' => SubjectResource::getUrl()],
                    ['label' => 'Jam Pelajaran', 'icon' => Heroicon::OutlinedClock, 'url' => TimeSlotResource::getUrl()],
                    ['label' => 'Rutinitas Harian', 'icon' => Heroicon::OutlinedQueueList, 'url' => DailyRoutineResource::getUrl()],
                    ['label' => 'Tahun Ajaran & Semester', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => SemesterResource::getUrl()],
                ],
            ],
            [
                'phase' => 'Fase 2 — Modul Harian',
                'status' => 'Sedang Dikerjakan',
                'modules' => [
                    ['label' => 'Kalender Akademik', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => CalendarDayResource::getUrl()],
                    ['label' => 'Jadwal Pelajaran', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => ScheduleResource::getUrl()],
                    ['label' => 'Matriks Jadwal', 'icon' => Heroicon::OutlinedSquares2x2, 'url' => ScheduleMatrix::getUrl()],
                    ['label' => 'Jadwal Saya', 'icon' => Heroicon::OutlinedMap, 'url' => MySchedule::getUrl()],
                    ['label' => 'Absensi Guru', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => TeacherAttendanceResource::getUrl()],
                    ['label' => 'Absensi Saya', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => AttendanceSelf::getUrl()],
                    ['label' => 'Penggantian Guru', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionResource::getUrl()],
                    ['label' => 'Penggantian Saya', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionSelf::getUrl()],
                    ['label' => 'Jurnal Guru', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalResource::getUrl()],
                    ['label' => 'Jurnal Saya', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalSelf::getUrl()],
                    ['label' => 'Manajemen Tugas', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => TaskResource::getUrl()],
                    ['label' => 'Tugas Tambahan', 'icon' => Heroicon::OutlinedBriefcase, 'url' => AdditionalDutyResource::getUrl()],
                    ['label' => 'Tugas Saya', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => MyTasks::getUrl()],
                    ['label' => 'Profil', 'icon' => Heroicon::OutlinedUserCircle, 'url' => MyProfile::getUrl()],
                ],
            ],
            [
                'phase' => 'Fase 3 — Program & Layanan',
                'status' => 'Selesai',
                'modules' => [
                    ['label' => 'Jadwal Kokurikuler', 'icon' => Heroicon::OutlinedRectangleStack, 'url' => CocurricularScheduleResource::getUrl()],
                    ['label' => 'Kokurikuler', 'icon' => Heroicon::OutlinedPuzzlePiece, 'url' => CocurricularResource::getUrl()],
                    ['label' => 'Kurikulum', 'icon' => Heroicon::OutlinedBookOpen, 'url' => CurriculumResource::getUrl()],
                    ['label' => 'Jadwal Ekstrakurikuler', 'icon' => Heroicon::OutlinedSparkles, 'url' => ExtracurricularResource::getUrl()],
                    ['label' => 'Absensi Ekstrakurikuler', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => ExtracurricularAttendanceResource::getUrl()],
                    ['label' => 'Papan Ekstrakurikuler', 'icon' => Heroicon::OutlinedMegaphone, 'url' => ExtracurricularWall::getUrl()],
                    ['label' => 'Penilaian Sumatif', 'icon' => Heroicon::OutlinedDocumentText, 'url' => AssessmentResource::getUrl()],
                    ['label' => 'PID', 'icon' => Heroicon::OutlinedComputerDesktop, 'url' => PidReservationResource::getUrl()],
                    ['label' => 'Outing Class', 'icon' => Heroicon::OutlinedTruck, 'url' => OutingClassResource::getUrl()],
                    ['label' => 'Supervisi', 'icon' => Heroicon::OutlinedEye, 'url' => SupervisionResource::getUrl()],
                    ['label' => 'Supervisi Saya', 'icon' => Heroicon::OutlinedEye, 'url' => MySupervisions::getUrl()],
                ],
            ],
            [
                'phase' => 'Fase 4 — Monitoring',
                'status' => 'Segera Hadir',
                'modules' => [
                    ['label' => 'Monev', 'icon' => Heroicon::OutlinedChartBar, 'url' => null],
                    ['label' => 'Monitoring Administrasi Kelas', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => null],
                    ['label' => 'Notifikasi Otomatis', 'icon' => Heroicon::OutlinedBell, 'url' => null],
                ],
            ],
        ];
    }
}
