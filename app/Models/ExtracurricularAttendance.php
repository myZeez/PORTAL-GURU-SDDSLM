<?php

namespace App\Models;

use App\Enums\ExtracurricularAttendanceStatus;
use Carbon\Carbon;
use Database\Factories\ExtracurricularAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['extracurricular_id', 'teacher_id', 'date', 'status'])]
class ExtracurricularAttendance extends Model
{
    /** @use HasFactory<ExtracurricularAttendanceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $attendance): void {
            $attendance->teacher_id ??= auth()->id();
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
            'status' => ExtracurricularAttendanceStatus::class,
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
     * Get the extracurricular this attendance record belongs to.
     *
     * @return BelongsTo<Extracurricular, $this>
     */
    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    /**
     * Get the teacher this attendance record was logged for.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
