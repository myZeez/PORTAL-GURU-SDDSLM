<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceReopeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A past date on which teachers may still fill in the attendance they missed. Normally
 * attendance can only be filled on the day itself; Waka Kurikulum reopens a date, with a
 * reason, and closes it again once teachers have caught up.
 */
#[Fillable(['date', 'reason', 'opened_by', 'closed_at', 'closed_by'])]
class AttendanceReopening extends Model
{
    /** @use HasFactory<AttendanceReopeningFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
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
     * Reopenings that are still open.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * Whether teachers may currently fill their own attendance for the given date.
     */
    public static function isOpenFor(string|CarbonInterface $date): bool
    {
        $date = is_string($date) ? $date : $date->toDateString();

        return static::query()->open()->whereDate('date', $date)->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
