<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AdditionalDuties\AdditionalDutyResource;
use App\Filament\Resources\AssessmentMonitoring\AssessmentMonitoringResource;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\CalendarDays\CalendarDayResource;
use App\Filament\Resources\Classrooms\ClassroomResource;
use App\Filament\Resources\Cocurriculars\CocurricularResource;
use App\Filament\Resources\CocurricularSchedules\CocurricularScheduleResource;
use App\Filament\Resources\Curricula\CurriculumResource;
use App\Filament\Resources\DailyRoutines\DailyRoutineResource;
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

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Every module in the panel, grouped the same way as the sidebar navigation (rather
     * than the project's internal build-phase order), since this page is the mobile
     * replacement for the sidebar rather than a development roadmap.
     *
     * @return array<int, array{group: string, modules: list<array{label: string, icon: Heroicon, url: ?string}>}>
     */
    public function getModuleGroups(): array
    {
        $groups = [
            [
                'group' => 'Saya',
                'modules' => [
                    ['label' => 'Jadwal Saya', 'icon' => Heroicon::OutlinedMap, 'url' => MySchedule::getUrl()],
                    ['label' => 'Absensi Saya', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => AttendanceSelf::getUrl()],
                    ['label' => 'Penggantian Saya', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionSelf::getUrl()],
                    ['label' => 'Jurnal Saya', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalSelf::getUrl()],
                    ['label' => 'Checklist SIMPATI', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => SimpatiChecklist::getUrl(), 'visible' => SimpatiChecklist::canAccess()],
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
                    ['label' => 'Supervisi', 'icon' => Heroicon::OutlinedEye, 'url' => SupervisionResource::getUrl(), 'visible' => SupervisionResource::canAccess()],
                    ['label' => 'Outing Class', 'icon' => Heroicon::OutlinedTruck, 'url' => OutingClassResource::getUrl()],
                    ['label' => 'PID', 'icon' => Heroicon::OutlinedComputerDesktop, 'url' => PidReservationResource::getUrl()],
                    ['label' => 'Jadwal Kokurikuler', 'icon' => Heroicon::OutlinedRectangleStack, 'url' => CocurricularScheduleResource::getUrl(), 'visible' => CocurricularScheduleResource::canAccess()],
                    ['label' => 'Kokurikuler', 'icon' => Heroicon::OutlinedPuzzlePiece, 'url' => CocurricularResource::getUrl(), 'visible' => CocurricularResource::canAccess()],
                    ['label' => 'Jadwal Ekstrakurikuler', 'icon' => Heroicon::OutlinedSparkles, 'url' => ExtracurricularResource::getUrl()],
                ],
            ],
            [
                'group' => 'Akademik',
                'modules' => [
                    ['label' => 'Kalender Akademik', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => CalendarDayResource::getUrl(), 'visible' => CalendarDayResource::canAccess()],
                    ['label' => 'Jadwal Pelajaran', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => ScheduleResource::getUrl(), 'visible' => ScheduleResource::canAccess()],
                    ['label' => 'Matriks Jadwal', 'icon' => Heroicon::OutlinedSquares2x2, 'url' => ScheduleMatrix::getUrl()],
                ],
            ],
            [
                'group' => 'Monitoring',
                'modules' => [
                    ['label' => 'Monitoring Kelas', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => MonitoringScheduleResource::getUrl(), 'visible' => MonitoringScheduleResource::canAccess()],
                    ['label' => 'Monev', 'icon' => Heroicon::OutlinedChartBar, 'url' => Monev::getUrl()],
                    ['label' => 'Performance Guru', 'icon' => Heroicon::OutlinedChartBarSquare, 'url' => PerformanceDashboard::getUrl()],
                    ['label' => 'Penilaian Sumatif', 'icon' => Heroicon::OutlinedDocumentChartBar, 'url' => AssessmentMonitoringResource::getUrl(), 'visible' => AssessmentMonitoringResource::canAccess()],
                ],
            ],
            [
                'group' => 'Administrasi Harian',
                'modules' => [
                    ['label' => 'Absensi Guru', 'icon' => Heroicon::OutlinedClipboardDocumentCheck, 'url' => TeacherAttendanceResource::getUrl(), 'visible' => TeacherAttendanceResource::canAccess()],
                    ['label' => 'Penggantian Guru', 'icon' => Heroicon::OutlinedArrowsRightLeft, 'url' => SubstitutionResource::getUrl(), 'visible' => SubstitutionResource::canAccess()],
                    ['label' => 'Jurnal Guru', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => JournalResource::getUrl(), 'visible' => JournalResource::canAccess()],
                    ['label' => 'Manajemen Tugas', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => TaskResource::getUrl(), 'visible' => TaskResource::canAccess()],
                    ['label' => 'Tugas Tambahan', 'icon' => Heroicon::OutlinedBriefcase, 'url' => AdditionalDutyResource::getUrl(), 'visible' => AdditionalDutyResource::canAccess()],
                ],
            ],
            [
                'group' => 'Data Master',
                'modules' => [
                    ['label' => 'Guru & Staf', 'icon' => Heroicon::OutlinedUsers, 'url' => UserResource::getUrl(), 'visible' => UserResource::canAccess()],
                    ['label' => 'Rombel', 'icon' => Heroicon::OutlinedAcademicCap, 'url' => ClassroomResource::getUrl(), 'visible' => ClassroomResource::canAccess()],
                    ['label' => 'Mata Pelajaran', 'icon' => Heroicon::OutlinedBookOpen, 'url' => SubjectResource::getUrl(), 'visible' => SubjectResource::canAccess()],
                    ['label' => 'Jam Pelajaran', 'icon' => Heroicon::OutlinedClock, 'url' => TimeSlotResource::getUrl(), 'visible' => TimeSlotResource::canAccess()],
                    ['label' => 'Rutinitas Harian', 'icon' => Heroicon::OutlinedQueueList, 'url' => DailyRoutineResource::getUrl(), 'visible' => DailyRoutineResource::canAccess()],
                    ['label' => 'Tahun Ajaran & Semester', 'icon' => Heroicon::OutlinedCalendarDays, 'url' => SemesterResource::getUrl(), 'visible' => SemesterResource::canAccess()],
                ],
            ],
        ];

        return collect($groups)
            ->map(function (array $group): array {
                $group['modules'] = collect($group['modules'])
                    ->filter(fn (array $module): bool => $module['visible'] ?? true)
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group): bool => $group['modules'] !== [])
            ->values()
            ->all();
    }
}
