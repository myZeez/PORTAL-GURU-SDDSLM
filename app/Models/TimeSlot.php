<?php

namespace App\Models;

use Database\Factories\TimeSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['starts_at', 'ends_at'])]
class TimeSlot extends Model
{
    /** @use HasFactory<TimeSlotFactory> */
    use HasFactory;

    /**
     * Get the daily routines that take place in this slot.
     *
     * @return HasMany<DailyRoutine, $this>
     */
    public function dailyRoutines(): HasMany
    {
        return $this->hasMany(DailyRoutine::class);
    }

    /**
     * Get the label used across the school, e.g. "06.00–06.40".
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => self::formatTime($this->starts_at).'–'.self::formatTime($this->ends_at));
    }

    /**
     * Format a database time ("06:00:00") the way the school writes it ("06.00").
     */
    private static function formatTime(string $time): string
    {
        return str_replace(':', '.', substr($time, 0, 5));
    }
}
