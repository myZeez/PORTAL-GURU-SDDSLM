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

        static::saved(function (self $calendarDay): void {
            if ($calendarDay->wasRecentlyCreated || $calendarDay->wasChanged(['status', 'notes'])) {
                $calendarDay->logChange(
                    $calendarDay->wasRecentlyCreated ? CalendarDayStatus::Efektif : $calendarDay->getOriginal('status'),
                    $calendarDay->status,
                );
            }
        });

        static::deleted(function (self $calendarDay): void {
            $calendarDay->logChange($calendarDay->status, CalendarDayStatus::Efektif);
        });
    }

    /**
     * Record a change to this date in the append-only history, whoever made it through
     * the app (changes made with nobody signed in, such as seeding, are not logged).
     */
    private function logChange(CalendarDayStatus $before, CalendarDayStatus $after): void
    {
        if (auth()->id() === null) {
            return;
        }

        CalendarDayLog::create([
            'date' => $this->date,
            'actor_id' => auth()->id(),
            'before_status' => $before->value,
            'after_status' => $after->value,
            'notes' => $this->notes ?? '',
        ]);
    }

    /**
     * Mark a date with a status, or return it to a plain effective day (which removes the
     * exception). Every change is written to the history with its explanation.
     */
    public static function markDate(string $date, CalendarDayStatus $status, string $notes): void
    {
        $existing = static::query()->whereDate('date', $date)->first();

        if ($status === CalendarDayStatus::Efektif) {
            if ($existing) {
                $existing->notes = $notes;
                $existing->saveQuietly();
                $existing->delete();
            }

            return;
        }

        if ($existing) {
            $existing->update(['status' => $status, 'notes' => $notes]);

            return;
        }

        $semester = Semester::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first() ?? Semester::current();

        abort_if($semester === null, 422, 'Belum ada semester untuk tanggal ini.');

        static::create([
            'semester_id' => $semester->id,
            'date' => $date,
            'status' => $status,
            'notes' => $notes,
        ]);
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
