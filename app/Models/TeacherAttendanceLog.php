<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TeacherAttendanceLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the audit trail of attendance that someone filled in or corrected on a
 * teacher's behalf. Entries are append-only: an ordinary update or delete is refused so
 * the history cannot be rewritten through the application.
 */
#[Fillable(['teacher_attendance_id', 'teacher_id', 'date', 'actor_id', 'action', 'before', 'after', 'reason'])]
class TeacherAttendanceLog extends Model
{
    /** @use HasFactory<TeacherAttendanceLogFactory> */
    use HasFactory;

    public const ACTION_CREATED = 'created';

    public const ACTION_CORRECTED = 'corrected';

    public const ACTION_DELETED = 'deleted';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Attendance history cannot be edited.');
        });

        static::deleting(function (): never {
            throw new LogicException('Attendance history cannot be deleted.');
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
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * `date` is handled by hand, like every other date column here, so SQLite (used in
     * tests) and MySQL both store a plain `Y-m-d`.
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
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<TeacherAttendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(TeacherAttendance::class, 'teacher_attendance_id');
    }
}
