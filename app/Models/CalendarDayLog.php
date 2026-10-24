<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CalendarDayLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the audit trail of changes to a date's calendar status (holiday, special
 * activity, ...). Append-only, like {@see TeacherAttendanceLog}.
 */
#[Fillable(['date', 'actor_id', 'before_status', 'after_status', 'notes'])]
class CalendarDayLog extends Model
{
    /** @use HasFactory<CalendarDayLogFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Calendar history cannot be edited.');
        });

        static::deleting(function (): never {
            throw new LogicException('Calendar history cannot be deleted.');
        });
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
