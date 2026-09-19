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
use App\Filament\Resources\MonitoringSchedules\MonitoringScheduleResource;
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
     * Every module in the panel, grouped the same way as the sidebar navigation (rather
     * than the project's internal build-phase order), since this page is the mobile
     * replacement for the sidebar rather than a development roadmap.
     *
     * @return array<int, array{group: string, modules: list<array{label: string, icon: Heroicon, url: ?string}>}>
     */
    public function getModuleGroups(): array
    {
        return [
            [
                'group' => 'Saya',
                'modules' => [
                    ['label' => 'Jadwal Saya', 'icon' => Heroicon::OutlinedMap, 'url' => MySchedule::getUrl()],
                    ['label' => 'Absensi Saya', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => AttendanceSelf::getUrl()],
                    ['label' => 'Penggantian Saya', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionSelf::getUrl()],
                    ['label' => 'Jurnal Saya', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalSelf::getUrl()],
                    ['label' => 'Tugas Saya', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => MyTasks::getUrl()],
                    ['label' => 'Profil', 'icon' => Heroicon::OutlinedUserCircle, 'url' => MyProfile::getUrl()],
                    ['label' => 'Ubah Password', 'icon' => Heroicon::OutlinedKey, 'url' => ChangePassword::getUrl()],
                    ['label' => 'Supervisi Saya', 'icon' => Heroicon::OutlinedEye, 'url' => MySupervisions::getUrl()],
                    ['label' => 'Papan Ekstrakurikuler', 'icon' => Heroicon::OutlinedMegaphone, 'url' => ExtracurricularWall::getUrl()],
                ],
            ],
            [
                'group' => 'Program & Layanan',
                'modules' => [
                    ['label' => 'Penilaian Sumatif', 'icon' => Heroicon::OutlinedDocumentText, 'url' => AssessmentResource::getUrl()],
                    ['label' => 'Kurikulum', 'icon' => Heroicon::OutlinedBookOpen, 'url' => CurriculumResource::getUrl()],
                    ['label' => 'Supervisi', 'icon' => Heroicon::OutlinedEye, 'url' => SupervisionResource::getUrl()],
                    ['label' => 'Outing Class', 'icon' => Heroicon::OutlinedTruck, 'url' => OutingClassResource::getUrl()],
                    ['label' => 'PID', 'icon' => Heroicon::OutlinedComputerDesktop, 'url' => PidReservationResource::getUrl()],
                    ['label' => 'Jadwal Kokurikuler', 'icon' => Heroicon::OutlinedRectangleStack, 'url' => CocurricularScheduleResource::getUrl()],
                    ['label' => 'Kokurikuler', 'icon' => Heroicon::OutlinedPuzzlePiece, 'url' => CocurricularResource::getUrl()],
                    ['label' => 'Jadwal Ekstrakurikuler', 'icon' => Heroicon::OutlinedSparkles, 'url' => ExtracurricularResource::getUrl()],
                    ['label' => 'Absensi Ekstrakurikuler', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => ExtracurricularAttendanceResource::getUrl()],
                ],
            ],
            [
                'group' => 'Akademik',
                'modules' => [
                    ['label' => 'Kalender Akademik', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => CalendarDayResource::getUrl()],
                    ['label' => 'Jadwal Pelajaran', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => ScheduleResource::getUrl()],
                    ['label' => 'Matriks Jadwal', 'icon' => Heroicon::OutlinedSquares2x2, 'url' => ScheduleMatrix::getUrl()],
                ],
            ],
            [
                'group' => 'Monitoring',
                'modules' => [
                    ['label' => 'Monitoring Kelas', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => MonitoringScheduleResource::getUrl()],
                    ['label' => 'Monev', 'icon' => Heroicon::OutlinedChartBar, 'url' => Monev::getUrl()],
                ],
            ],
            [
                'group' => 'Administrasi Harian',
                'modules' => [
                    ['label' => 'Absensi Guru', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => TeacherAttendanceResource::getUrl()],
                    ['label' => 'Penggantian Guru', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionResource::getUrl()],
                    ['label' => 'Jurnal Guru', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalResource::getUrl()],
                    ['label' => 'Manajemen Tugas', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => TaskResource::getUrl()],
                    ['label' => 'Tugas Tambahan', 'icon' => Heroicon::OutlinedBriefcase, 'url' => AdditionalDutyResource::getUrl()],
                ],
            ],
            [
                'group' => 'Data Master',
                'modules' => [
                    ['label' => 'Guru & Staf', 'icon' => Heroicon::OutlinedUsers, 'url' => UserResource::getUrl()],
                    ['label' => 'Rombel', 'icon' => Heroicon::OutlinedAcademicCap, 'url' => ClassroomResource::getUrl()],
                    ['label' => 'Mata Pelajaran', 'icon' => Heroicon::OutlinedBookOpen, 'url' => SubjectResource::getUrl()],
                    ['label' => 'Jam Pelajaran', 'icon' => Heroicon::OutlinedClock, 'url' => TimeSlotResource::getUrl()],
                    ['label' => 'Rutinitas Harian', 'icon' => Heroicon::OutlinedQueueList, 'url' => DailyRoutineResource::getUrl()],
                    ['label' => 'Tahun Ajaran & Semester', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => SemesterResource::getUrl()],
                ],
            ],
        ];
    }
}
