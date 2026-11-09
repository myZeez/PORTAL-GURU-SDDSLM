<?php

namespace App\Models;

use App\Enums\SchoolDay;
use Carbon\CarbonInterface;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['semester_id', 'day_of_week', 'time_slot_id', 'classroom_id', 'subject_id', 'teacher_id'])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    /**
     * A subject taught by a team has no individual teacher, whatever the caller sent.
     */
    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            if ($schedule->subject_id && Subject::query()->whereKey($schedule->subject_id)->whereNotNull('team_name')->where('team_name', '!=', '')->exists()) {
                $schedule->teacher_id = null;
            }
        });
    }

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
     * Get the substitutions recorded against this slot, across all dates.
     *
     * @return HasMany<Substitution, $this>
     */
    public function substitutions(): HasMany
    {
        return $this->hasMany(Substitution::class);
    }

    /**
     * Get the teaching hours (JP) this slot counts for: 1, unless the subject is exempt
     * (Penguatan Hafalan, PRAMUKA) or taught by a team (UMMI), which count 0.
     *
     * @return Attribute<int, never>
     */
    protected function jp(): Attribute
    {
        return Attribute::get(fn (): int => $this->teacher_id !== null && $this->subject?->counts_toward_teaching_load ? 1 : 0);
    }

    /**
     * Who teaches the slot, as the school reads it: the teacher's name, or the team's name
     * for a subject taught by a team.
     *
     * @return Attribute<string, never>
     */
    protected function teacherName(): Attribute
    {
        return Attribute::get(fn (): string => $this->teacher?->name ?? $this->subject?->team_name ?? '—');
    }

    /**
     * Whether a subject may be taught to several classes at once (PJOK is the example), on
     * any day. Even then a teacher only doubles up with the same subject: see
     * {@see self::hasTeacherConflict()}.
     */
    public static function isConflictExempt(Subject $subject): bool
    {
        return (bool) $subject->allows_concurrent_scheduling;
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
        if (self::isConflictExempt($subject)) {
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
     * Determine whether this exact lesson (same class, slot, subject and teacher) is already
     * scheduled. The parallel-subject exemption lets a class hold two lessons in a slot, but never the
     * same lesson twice.
     */
    public static function existsExactly(
        int $semesterId,
        SchoolDay $day,
        int $timeSlotId,
        int $classroomId,
        int $subjectId,
        ?int $teacherId,
    ): bool {
        return static::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day->value)
            ->where('time_slot_id', $timeSlotId)
            ->where('classroom_id', $classroomId)
            ->where('subject_id', $subjectId)
            ->when($teacherId, fn ($query) => $query->where('teacher_id', $teacherId), fn ($query) => $query->whereNull('teacher_id'))
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
        return static::query()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $day->value)
            ->where('time_slot_id', $timeSlotId)
            ->where('teacher_id', $teacherId)
            ->when(self::isConflictExempt($subject), fn ($query) => $query->where('subject_id', '!=', $subject->id))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Split lessons into runs of back-to-back slots for the same classroom and subject, e.g.
     * 07.30–08.00 + 08.00–08.30 is one run. A gap, another classroom or another subject
     * starts a new run. Used to fill one journal for a whole run.
     *
     * @param  Collection<int, self>  $schedules
     * @return list<Collection<int, self>>
     */
    public static function groupConsecutive(Collection $schedules): array
    {
        $groups = [];
        $current = null;

        foreach ($schedules->load('timeSlot')->sortBy(fn (self $schedule): string => $schedule->timeSlot->starts_at) as $schedule) {
            $last = $current?->last();

            if (
                $last
                && $last->classroom_id === $schedule->classroom_id
                && $last->subject_id === $schedule->subject_id
                && $last->timeSlot->ends_at === $schedule->timeSlot->starts_at
            ) {
                $current->push($schedule);

                continue;
            }

            $current = new Collection([$schedule]);
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * Get the slots a teacher is effectively responsible for on a given date: their own
     * schedule for that day of week, minus any slot substituted away to someone else,
     * plus any other teacher's slot they are covering as a substitute that date.
     *
     * @return Collection<int, self>
     */
    public static function effectiveForTeacherOnDate(int $teacherId, CarbonInterface $date): Collection
    {
        $dayOfWeek = $date->dayOfWeekIso;
        $dateString = $date->toDateString();

        $own = static::query()
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->whereDoesntHave('substitutions', fn ($query) => $query->where('date', $dateString))
            ->get();

        $covering = static::query()
            ->where('day_of_week', $dayOfWeek)
            ->whereHas('substitutions', fn ($query) => $query
                ->where('date', $dateString)
                ->where('substitute_teacher_id', $teacherId))
            ->get();

        return $own->concat($covering);
    }
}
