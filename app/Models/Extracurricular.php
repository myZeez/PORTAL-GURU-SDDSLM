<?php

namespace App\Models;

use App\Enums\SchoolDay;
use Database\Factories\ExtracurricularFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An extracurricular's weekly schedule: when it meets, where, and which teachers (guru
 * pendamping) and outside coach (pelatih luar) run it.
 */
#[Fillable(['name', 'day', 'start_time', 'end_time', 'location', 'external_coach', 'created_by'])]
class Extracurricular extends Model
{
    /** @use HasFactory<ExtracurricularFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $extracurricular): void {
            $extracurricular->created_by ??= auth()->id();
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
            'day' => SchoolDay::class,
        ];
    }

    /**
     * Get the teachers assigned to accompany this extracurricular (guru pendamping).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assistantTeachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'extracurricular_teacher', 'extracurricular_id', 'teacher_id');
    }

    /**
     * Get the attendance records logged for this extracurricular.
     *
     * @return HasMany<ExtracurricularAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(ExtracurricularAttendance::class);
    }

    /**
     * Get the user who created this schedule entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
