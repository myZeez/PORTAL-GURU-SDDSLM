<?php

namespace App\Models;

use App\Enums\SchoolDay;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['semester_id', 'day_of_week', 'time_slot_id', 'classroom_id', 'subject_id', 'teacher_id'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => SchoolDay::class,
        ];
    }

    /**
     * Get the semester this slot belongs to.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the time slot this schedule occupies.
     *
     * @return BelongsTo<TimeSlot, $this>
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /**
     * Get the classroom being taught.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the subject being taught.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher assigned to this slot.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the teaching hours (JP) this slot counts for: 1, unless the subject is exempt
     * (Penguatan Hafalan, PRAMUKA), which count 0.
     *
     * @return Attribute<int, never>
     */
    protected function jp(): Attribute
    {
        return Attribute::get(fn (): int => $this->subject?->counts_toward_teaching_load ? 1 : 0);
    }

    /**
     * Whether a day + subject combination is exempt from clash checking: some subjects
     * (PJOK, PRAMUKA) are deliberately taught to several classes at once on Kamis, so a
     * teacher or classroom appearing more than once in the same slot that day is expected.
     */
    public static function isConflictExempt(SchoolDay $day, Subject $subject): bool
    {
        return $day === SchoolDay::Kamis && $subject->allows_concurrent_scheduling;
    }

    /**
     * Determine whether another schedule already occupies this classroom's slot.
     */
    public static function hasClassroomConflict(
        int $semesterId,
        SchoolDay $day,
        int $timeSlotId,
        int $classroomId,
        Subject $subject,
        ?int $ignoreId = null,
    ): bool {
        if (self::isConflictExempt($day, $subject)) {
            return false;
        }

        return static::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day->value)
            ->where('time_slot_id', $timeSlotId)
            ->where('classroom_id', $classroomId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Determine whether the teacher is already teaching another class in this slot.
     */
    public static function hasTeacherConflict(
        int $semesterId,
        SchoolDay $day,
        int $timeSlotId,
        int $teacherId,
        Subject $subject,
        ?int $ignoreId = null,
    ): bool {
        if (self::isConflictExempt($day, $subject)) {
            return false;
        }

        return static::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day->value)
            ->where('time_slot_id', $timeSlotId)
            ->where('teacher_id', $teacherId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
