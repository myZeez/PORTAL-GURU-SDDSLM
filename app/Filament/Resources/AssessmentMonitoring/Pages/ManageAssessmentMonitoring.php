<?php

namespace App\Filament\Resources\AssessmentMonitoring\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Resources\AssessmentMonitoring\AssessmentMonitoringResource;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\User;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Collection;

class ManageAssessmentMonitoring extends ManageRecords
{
    protected static string $resource = AssessmentMonitoringResource::class;

    protected static ?string $title = 'Monitoring Penilaian Sumatif';

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(Assessment::class, 'Riwayat perubahan penilaian sumatif'),
        ];
    }

    /**
     * Which classes and teachers have entered at least one summative assessment in the
     * running semester (or ever, while no semester is set), and which have not.
     *
     * @return array{
     *     semester: ?string,
     *     assessments: int,
     *     completed: int,
     *     classrooms: array{total: int, done: int, missing: Collection<int, Classroom>},
     *     teachers: array{total: int, done: int, missing: Collection<int, User>},
     * }
     */
    public function getCoverage(): array
    {
        $semester = Semester::current();

        $assessments = Assessment::query()
            ->when($semester?->starts_on, fn ($query) => $query->whereBetween('date', [$semester->starts_on->toDateString(), $semester->effectiveEndsOn()->toDateString()]))
            ->get(['classroom_id', 'teacher_id', 'is_completed']);

        $classrooms = Classroom::query()->orderBy('grade')->orderBy('code')->get();
        $doneClassroomIds = $assessments->pluck('classroom_id')->unique();

        $teacherIds = $semester
            ? Schedule::query()->where('semester_id', $semester->id)->whereNotNull('teacher_id')->distinct()->pluck('teacher_id')
            : $assessments->pluck('teacher_id')->unique();
        $teachers = User::query()->whereKey($teacherIds)->where('is_active', true)->orderBy('name')->get();
        $doneTeacherIds = $assessments->pluck('teacher_id')->unique();

        return [
            'semester' => $semester?->name,
            'assessments' => $assessments->count(),
            'completed' => $assessments->where('is_completed', true)->count(),
            'classrooms' => [
                'total' => $classrooms->count(),
                'done' => $classrooms->whereIn('id', $doneClassroomIds)->count(),
                'missing' => $classrooms->whereNotIn('id', $doneClassroomIds)->values(),
            ],
            'teachers' => [
                'total' => $teachers->count(),
                'done' => $teachers->whereIn('id', $doneTeacherIds)->count(),
                'missing' => $teachers->whereNotIn('id', $doneTeacherIds)->values(),
            ],
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.assessment-monitoring.coverage'),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
