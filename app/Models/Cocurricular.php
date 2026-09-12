<?php

namespace App\Models;

use App\Enums\CocurricularActivityType;
use App\Enums\GraduateDimension;
use Database\Factories\CocurricularFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One classroom's submission for a given {@see CocurricularSchedule}: what was done,
 * and which "Dimensi Profil Lulusan" (see {@see GraduateDimension}) it
 * targeted.
 */
#[Fillable(['cocurricular_schedule_id', 'classroom_id', 'activity_type', 'description', 'dimensions', 'created_by'])]
class Cocurricular extends Model
{
    /** @use HasFactory<CocurricularFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $cocurricular): void {
            $cocurricular->created_by ??= auth()->id();
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
            'activity_type' => CocurricularActivityType::class,
            'dimensions' => 'array',
        ];
    }

    /**
     * Get the schedule this submission belongs to.
     *
     * @return BelongsTo<CocurricularSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CocurricularSchedule::class, 'cocurricular_schedule_id');
    }

    /**
     * Get the classroom that submitted this record.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the user who created this submission.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
