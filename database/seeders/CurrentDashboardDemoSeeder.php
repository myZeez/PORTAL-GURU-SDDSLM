<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\ExtracurricularAttendanceStatus;
use App\Enums\SchoolDay;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Substitution;
use App\Models\Task;
use App\Models\TeacherAttendance;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrentDashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->where('code', 'BR')->firstOrFail();
        $assistant = User::query()->whereKeyNot($teacher->id)->firstOrFail();
        $curriculumManager = User::query()->where('code', 'KN')->firstOrFail();
        $semester = Semester::current() ?? Semester::query()->latest('starts_on')->firstOrFail();
        $subject = Subject::query()->firstOrFail();
        $timeSlot = TimeSlot::query()->orderBy('starts_at')->firstOrFail();
        $today = now()->toDateString();
        $day = SchoolDay::from(now()->dayOfWeekIso);

        DB::transaction(function () use ($teacher, $assistant, $curriculumManager, $semester, $subject, $timeSlot, $today, $day): void {
            $classroom = Classroom::query()->firstOrCreate(
                ['code' => 'DEMO-01'],
                [
                    'name' => '[DEMO] Kelas Tampilan',
                    'grade' => 6,
                    'homeroom_teacher_id' => $teacher->id,
                    'assistant_teacher_id' => $assistant->id,
                ],
            );

            $schedule = Schedule::query()->firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'day_of_week' => $day->value,
                    'time_slot_id' => $timeSlot->id,
                    'classroom_id' => $classroom->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                ],
            );

            TeacherAttendance::query()->firstOrCreate(
                ['teacher_id' => $teacher->id, 'date' => $today],
                [
                    'status' => AttendanceStatus::Hadir,
                    'notes' => '[DEMO] Absensi dashboard',
                    'filled_at' => now(),
                    'created_by' => $teacher->id,
                ],
            );

            Journal::query()->firstOrCreate(
                ['schedule_id' => $schedule->id, 'date' => $today],
                [
                    'teacher_id' => $teacher->id,
                    'topic' => '[DEMO] Pembelajaran interaktif',
                    'description' => 'Data demonstrasi untuk melihat tampilan jurnal yang terisi penuh.',
                    'students_present' => 30,
                    'students_permitted' => 1,
                    'students_sick' => 1,
                    'students_absent' => 0,
                    'created_by' => $teacher->id,
                ],
            );

            Substitution::query()->firstOrCreate(
                ['schedule_id' => $schedule->id, 'date' => $today],
                [
                    'substitute_teacher_id' => $assistant->id,
                    'reason' => '[DEMO] Simulasi penggantian guru hari ini.',
                    'created_by' => $teacher->id,
                ],
            );

            Assessment::query()->firstOrCreate(
                ['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'date' => $today],
                ['chapter' => '[DEMO] Evaluasi pembelajaran', 'is_completed' => true],
            );

            $task = Task::query()->firstOrCreate(
                ['title' => '[DEMO] Lengkapi administrasi pembelajaran'],
                [
                    'description' => 'Data demonstrasi untuk melihat kartu tugas yang aktif.',
                    'starts_on' => now()->subDay(),
                    'due_on' => now()->addDays(3),
                    'created_by' => $curriculumManager->id,
                ],
            );
            $task->assignees()->syncWithoutDetaching([$teacher->id => ['is_completed' => false, 'completed_at' => null]]);

            $extracurricular = Extracurricular::query()->firstOrCreate(
                ['name' => '[DEMO] Robotik Tampilan'],
                [
                    'day' => $day,
                    'start_time' => '15:00',
                    'end_time' => '16:30',
                    'location' => '[DEMO] Laboratorium',
                    'created_by' => $curriculumManager->id,
                ],
            );
            $extracurricular->assistantTeachers()->syncWithoutDetaching([$teacher->id]);
            ExtracurricularAttendance::query()->firstOrCreate(
                ['extracurricular_id' => $extracurricular->id, 'teacher_id' => $teacher->id, 'date' => $today],
                ['status' => ExtracurricularAttendanceStatus::Hadir],
            );
        });
    }
}
