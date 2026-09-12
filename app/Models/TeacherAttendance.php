<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\SchoolDay;
use Carbon\Carbon;
use Database\Factories\TeacherAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teacher_id', 'date', 'status', 'leave_starts_on', 'leave_ends_on', 'notes', 'filled_at', 'created_by'])]
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
     * Whether teachers are expected to fill attendance on the given date at all: a school
     * day (Monday-Friday) whose calendar status requires it.
     */
    public static function isFillableDate(Carbon $date): bool
    {
        return SchoolDay::tryFrom($date->dayOfWeekIso) !== null
            && CalendarDay::statusFor($date)->requiresAttendance();
    }
}
