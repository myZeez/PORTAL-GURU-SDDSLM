<?php

namespace App\Models;

use App\Enums\CalendarDayStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\CalendarDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['semester_id', 'date', 'status', 'notes', 'created_by'])]
class CalendarDay extends Model
{
    /** @use HasFactory<CalendarDayFactory> */
    use HasFactory;

    /**
     * Record who marked the exception, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $calendarDay): void {
            $calendarDay->created_by ??= auth()->id();
        });
    }

    /**
     * Get the status of a date: the stored exception, or Efektif if the date has none.
     */
    public static function statusFor(string|CarbonInterface $date): CalendarDayStatus
    {
        $date = is_string($date) ? $date : $date->toDateString();

        return static::query()->whereDate('date', $date)->first()?->status ?? CalendarDayStatus::Efektif;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CalendarDayStatus::class,
        ];
    }

    /**
     * `date` is handled by hand rather than Eloquent's built-in `date` cast: that cast
     * still serializes through the model's datetime format (`Y-m-d H:i:s`) when saving,
     * which a real `DATE` column (MySQL) quietly truncates back to `Y-m-d` but a `TEXT`
     * column (SQLite, used in tests) stores verbatim — breaking the `unique` check
     * Filament runs against the raw `Y-m-d` the form submits. Storing `Y-m-d` explicitly
     * keeps both environments consistent.
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
     * Get the semester this date falls within.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the administrator who marked this date.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
