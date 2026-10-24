<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\SchoolDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\TeacherAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['teacher_id', 'date', 'status', 'leave_starts_on', 'leave_ends_on', 'early_leave_reason', 'notes', 'filled_at', 'created_by'])]
class TeacherAttendance extends Model
{
    /** @use HasFactory<TeacherAttendanceFactory> */
    use HasFactory;

    /**
     * Record who filled it and when, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $attendance): void {
            $attendance->created_by ??= auth()->id();
            $attendance->filled_at ??= now();
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
            'status' => AttendanceStatus::class,
            'filled_at' => 'datetime',
        ];
    }

    /**
     * `date`, `leave_starts_on` and `leave_ends_on` are handled by hand rather than
     * Eloquent's built-in `date` cast: that cast still serializes through the model's
     * datetime format (`Y-m-d H:i:s`) when saving, which a real `DATE` column (MySQL)
     * quietly truncates back to `Y-m-d` but a `TEXT` column (SQLite, used in tests)
     * stores verbatim. Storing `Y-m-d` explicitly keeps both environments consistent.
     *
     * @return Attribute<Carbon, string>
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string $value): string => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function leaveStartsOn(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function leaveEndsOn(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    private static function dateOnly(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string|null $value): ?string => filled($value) ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * Whether this attendance was filled after the date it is for.
     *
     * @return Attribute<bool, never>
     */
    protected function isLate(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->filled_at !== null
                && $this->date !== null
                && $this->filled_at->toDateString() > $this->date->toDateString()
        );
    }

    /**
     * Get the teacher this attendance belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the user who recorded this attendance.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the audit trail of times someone filled or corrected this day on the teacher's
     * behalf.
     *
     * @return HasMany<TeacherAttendanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(TeacherAttendanceLog::class)->latest('id');
    }

    /**
     * Whether teachers are expected to fill attendance on the given date at all: a school
     * day (Monday-Friday) whose calendar status requires it, or a weekend that an
     * administrator marked as an official activity (Kegiatan / Kegiatan Khusus). A plain
     * weekend, with no calendar entry, never counts.
     */
    public static function isFillableDate(Carbon $date): bool
    {
        return static::isFillableOn($date, CalendarDay::statusFor($date));
    }

    /**
     * The same rule as {@see self::isFillableDate()} for a date whose calendar status is
     * already known, so callers going through many dates need not query each one.
     */
    public static function isFillableOn(CarbonInterface $date, CalendarDayStatus $status): bool
    {
        if (SchoolDay::tryFrom($date->dayOfWeekIso) === null) {
            return $status !== CalendarDayStatus::Efektif && $status->requiresAttendance();
        }

        return $status->requiresAttendance();
    }

    /**
     * Fill in, or correct, a teacher's attendance on their behalf, and write who did it,
     * why, and what changed (before and after) to the audit trail in the same transaction.
     * An existing record for that teacher and date is corrected; otherwise one is created.
     *
     * @param  array{status: string, leave_starts_on?: ?string, leave_ends_on?: ?string, early_leave_reason?: ?string, notes?: ?string}  $attributes
     */
    public static function saveOnBehalf(User $actor, int $teacherId, string $date, array $attributes, string $reason): self
    {
        abort_unless($actor->canManageAttendance(), 403);

        return DB::transaction(function () use ($actor, $teacherId, $date, $attributes, $reason): self {
            $status = AttendanceStatus::from($attributes['status']);

            $values = [
                'status' => $status,
                'leave_starts_on' => $status === AttendanceStatus::Cuti ? ($attributes['leave_starts_on'] ?? null) : null,
                'leave_ends_on' => $status === AttendanceStatus::Cuti ? ($attributes['leave_ends_on'] ?? null) : null,
                'early_leave_reason' => $status === AttendanceStatus::PulangCepat ? ($attributes['early_leave_reason'] ?? null) : null,
                'notes' => $attributes['notes'] ?? null,
            ];

            $record = static::query()
                ->where('teacher_id', $teacherId)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            $before = $record?->snapshot();

            if ($record) {
                $record->fill($values)->save();
            } else {
                $record = new self([...$values, 'teacher_id' => $teacherId, 'date' => $date]);
                $record->created_by = $actor->id;
                $record->save();
            }

            TeacherAttendanceLog::create([
                'teacher_attendance_id' => $record->id,
                'teacher_id' => $teacherId,
                'date' => $date,
                'actor_id' => $actor->id,
                'action' => $before === null ? TeacherAttendanceLog::ACTION_CREATED : TeacherAttendanceLog::ACTION_CORRECTED,
                'before' => $before,
                'after' => $record->fresh()->snapshot(),
                'reason' => $reason,
            ]);

            return $record;
        });
    }

    /**
     * The fields worth keeping in the audit trail, as plain values.
     *
     * @return array{status: string, leave_starts_on: ?string, leave_ends_on: ?string, early_leave_reason: ?string, notes: ?string}
     */
    public function snapshot(): array
    {
        return [
            'status' => $this->status->value,
            'leave_starts_on' => $this->leave_starts_on?->toDateString(),
            'leave_ends_on' => $this->leave_ends_on?->toDateString(),
            'early_leave_reason' => $this->early_leave_reason,
            'notes' => $this->notes,
        ];
    }
}
