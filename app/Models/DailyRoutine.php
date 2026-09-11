<?php

namespace App\Models;

use App\Enums\SchoolDay;
use Database\Factories\DailyRoutineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day_of_week', 'time_slot_id', 'label'])]
class DailyRoutine extends Model
{
    /** @use HasFactory<DailyRoutineFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => SchoolDay::class,
        ];
    }

    /**
     * Get the time slot the routine occupies.
     *
     * @return BelongsTo<TimeSlot, $this>
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
