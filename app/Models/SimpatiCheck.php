<?php

namespace App\Models;

use App\Enums\CalendarDayStatus;
use App\Enums\SchoolDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\SimpatiCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Whether the SIMPATI portal was filled in for one class on one date, ticked by that
 * class's pendamping wali kelas. There is one row per class and date (enforced by a
 * unique index), so the same fact can never be recorded twice; every change is also
 * written to {@see SimpatiCheckLog}.
 */
#[Fillable(['classroom_id', 'date', 'is_filled', 'checked_by'])]
class SimpatiCheck extends Model
{
    /** @use HasFactory<SimpatiCheckFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_filled' => 'boolean',
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
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * The pendamping who last ticked or un-ticked it.
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * Whether SIMPATI must be filled on the given date: a school day that is not a
     * holiday, or a weekend an administrator marked as an official activity.
     */
    public static function isRequiredOn(CarbonInterface $date): bool
    {
        return static::isRequiredFor($date, CalendarDay::statusFor($date));
    }

    /**
     * The same rule as {@see self::isRequiredOn()} for a date whose calendar status is
     * already known, so callers going through many dates need not query each one.
     */
    public static function isRequiredFor(CarbonInterface $date, CalendarDayStatus $status): bool
    {
        if (SchoolDay::tryFrom($date->dayOfWeekIso) === null) {
            return $status !== CalendarDayStatus::Efektif && $status->requiresSimpati();
        }

        return $status->requiresSimpati();
    }

    /**
     * Every date in the range on which SIMPATI must be filled.
     *
     * @return SupportCollection<int, Carbon>
     */
    public static function requiredDatesBetween(CarbonInterface $from, CarbonInterface $to): SupportCollection
    {
        $dates = collect();

        for ($day = Carbon::parse($from->toDateString()); $day->lte($to); $day->addDay()) {
            if (static::isRequiredOn($day)) {
                $dates->push($day->copy());
            }
        }

        return $dates;
    }

    /**
     * Tick or un-tick a class's SIMPATI for a date. Nothing is written (and nothing is
     * logged) when the state already matches, so a double click leaves no noise behind.
     */
    public static function record(Classroom $classroom, CarbonInterface $date, bool $filled, User $actor): self
    {
        return DB::transaction(function () use ($classroom, $date, $filled, $actor): self {
            $check = static::query()
                ->where('classroom_id', $classroom->id)
                ->whereDate('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            $was = $check?->is_filled ?? false;

            if ($check && $was === $filled) {
                return $check;
            }

            $check ??= new self(['classroom_id' => $classroom->id, 'date' => $date]);
            $check->is_filled = $filled;
            $check->checked_by = $actor->id;
            $check->save();

            SimpatiCheckLog::create([
                'classroom_id' => $classroom->id,
                'date' => $date,
                'actor_id' => $actor->id,
                'was_filled' => $was,
                'is_filled' => $filled,
            ]);

            return $check;
        });
    }

    /**
     * How many of the school's classes have SIMPATI filled in for a date.
     *
     * @return array{required: bool, total: int, filled: int, missing: int, percentage: int, unfilled: Collection<int, Classroom>}
     */
    public static function summaryFor(CarbonInterface $date): array
    {
        $classrooms = Classroom::query()->with('assistantTeacher')->orderBy('grade')->orderBy('code')->get();
        $required = static::isRequiredOn($date);

        $filledIds = $required
            ? static::query()->whereDate('date', $date->toDateString())->where('is_filled', true)->pluck('classroom_id')
            : collect();

        $unfilled = $required
            ? $classrooms->reject(fn (Classroom $classroom): bool => $filledIds->contains($classroom->id))->values()
            : new Collection;
        $total = $classrooms->count();
        $filled = $required ? $total - $unfilled->count() : 0;

        return [
            'required' => $required,
            'total' => $total,
            'filled' => $filled,
            'missing' => $required ? $unfilled->count() : 0,
            'percentage' => $required && $total > 0 ? (int) round($filled / $total * 100) : 0,
            'unfilled' => $unfilled,
        ];
    }

    /**
     * How complete SIMPATI is across every class for the month containing the given day,
     * counting only required dates up to today.
     *
     * @return array{filled: int, possible: int, percentage: int}
     */
    public static function monthlySummaryFor(CarbonInterface $day): array
    {
        $from = Carbon::parse($day->toDateString())->startOfMonth();
        $to = $from->copy()->endOfMonth()->min(now()->startOfDay());

        $required = static::requiredDatesBetween($from, $to)->map(fn (Carbon $date): string => $date->toDateString());
        $possible = $required->count() * Classroom::query()->count();

        $filled = static::query()
            ->where('is_filled', true)
            ->whereIn('date', $required->all())
            ->count();

        return [
            'filled' => $filled,
            'possible' => $possible,
            'percentage' => $possible > 0 ? (int) round($filled / $possible * 100) : 0,
        ];
    }

    /**
     * How many of the required dates in a range a class has ticked.
     *
     * @return array{filled: int, required: int}
     */
    public static function completionFor(Classroom $classroom, CarbonInterface $from, CarbonInterface $to): array
    {
        $required = static::requiredDatesBetween($from, $to)->map(fn (Carbon $date): string => $date->toDateString());

        $filled = static::query()
            ->where('classroom_id', $classroom->id)
            ->where('is_filled', true)
            ->whereIn('date', $required->all())
            ->count();

        return ['filled' => $filled, 'required' => $required->count()];
    }
}
