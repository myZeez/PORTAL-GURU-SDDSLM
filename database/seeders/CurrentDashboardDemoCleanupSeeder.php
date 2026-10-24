<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Extracurricular;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrentDashboardDemoCleanupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $taskIds = Task::query()
                ->where('title', 'like', '[DEMO]%')
                ->pluck('id');

            DB::table('task_assignees')->whereIn('task_id', $taskIds)->delete();
            Task::query()->whereKey($taskIds)->delete();

            $extracurricularIds = Extracurricular::query()
                ->where('name', 'like', '[DEMO]%')
                ->pluck('id');

            DB::table('extracurricular_attendances')->whereIn('extracurricular_id', $extracurricularIds)->delete();
            DB::table('extracurricular_teacher')->whereIn('extracurricular_id', $extracurricularIds)->delete();
            Extracurricular::query()->whereKey($extracurricularIds)->delete();

            $classroomId = Classroom::query()->where('code', 'DEMO-01')->value('id');

            if ($classroomId === null) {
                return;
            }

            $scheduleIds = DB::table('schedules')
                ->where('classroom_id', $classroomId)
                ->pluck('id');

            DB::table('assessments')->where('classroom_id', $classroomId)->delete();
            DB::table('substitutions')->whereIn('schedule_id', $scheduleIds)->delete();
            DB::table('journals')->whereIn('schedule_id', $scheduleIds)->delete();
            DB::table('teacher_attendances')->where('notes', '[DEMO] Absensi dashboard')->delete();
            DB::table('schedules')->whereIn('id', $scheduleIds)->delete();
            DB::table('classrooms')->where('id', $classroomId)->delete();
        });
    }
}
