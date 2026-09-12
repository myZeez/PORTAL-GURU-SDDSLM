<?php

namespace App\Models;

use App\Enums\OutingStatus;
use Carbon\Carbon;
use Database\Factories\PidReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'starts_at', 'ends_at', 'location', 'subject_id', 'purpose', 'status', 'requested_by'])]
class PidReservation extends Model
{
    /** @use HasFactory<PidReservationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $reservation): void {
            $reservation->requested_by ??= auth()->id();
            $reservation->status ??= OutingStatus::Menunggu;
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
            'status' => OutingStatus::class,
        ];
    }

    /**
     * `date` is handled by hand rather than Eloquent's built-in `date` cast: that cast
     * still serializes through the model's datetime format (`Y-m-d H:i:s`) when saving,
     * which a real `DATE` column (MySQL) quietly truncates back to `Y-m-d` but a `TEXT`
     * column (SQLite, used in tests) stores verbatim.
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
     * Normalized to `H:i:s` on write so a `starts_at`/`ends_at` submitted without
     * seconds (e.g. "09:00" from a time picker) always compares correctly against
     * values already stored with seconds — SQLite (used in tests) stores `time()`
     * columns as plain text, so "09:00" and "09:00:00" would otherwise compare as
     * different, unequal strings instead of the same moment.
     *
     * @return Attribute<string, string>
     */
    protected function startsAt(): Attribute
    {
        return self::timeOnly();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function endsAt(): Attribute
    {
        return self::timeOnly();
    }

    /**
     * @return Attribute<string, string>
     */
    private static function timeOnly(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Carbon::parse($value)->format('H:i:s'),
        );
    }

    /**
     * Get the subject the PID is needed for.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the user who submitted this reservation.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Determine whether another reservation already occupies this location for an
     * overlapping time on the same date. Any existing reservation blocks a new one,
     * whether it has been approved yet or is still pending — the scope's "ditolak kalau
     * slot sudah dipesan" applies as soon as a slot is claimed, not only once approved.
     */
    public static function hasConflict(
        string $date,
        string $location,
        string $startsAt,
        string $endsAt,
        ?int $ignoreId = null,
    ): bool {
        // Normalized to match how starts_at/ends_at are stored (see timeOnly()) — a
        // time picker submitting "09:00" must compare equal to a stored "09:00:00".
        $startsAt = Carbon::parse($startsAt)->format('H:i:s');
        $endsAt = Carbon::parse($endsAt)->format('H:i:s');

        return static::query()
            ->whereDate('date', $date)
            ->where('location', $location)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
