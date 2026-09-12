<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CocurricularScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single kokurikuler activity day set by administrators, with a theme and a set of
 * target classrooms. Each target classroom submits its own {@see Cocurricular} record
 * against this schedule.
 */
#[Fillable(['date', 'theme', 'created_by'])]
class CocurricularSchedule extends Model
{
    /** @use HasFactory<CocurricularScheduleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            $schedule->created_by ??= auth()->id();
        });
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
     * Get the classrooms targeted by this schedule.
     *
     * @return BelongsToMany<Classroom, $this>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'cocurricular_schedule_classroom');
    }

    /**
     * Get the submissions recorded against this schedule.
     *
     * @return HasMany<Cocurricular, $this>
     */
    public function cocurriculars(): HasMany
    {
        return $this->hasMany(Cocurricular::class);
    }

    /**
     * Get the user who created this schedule.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
