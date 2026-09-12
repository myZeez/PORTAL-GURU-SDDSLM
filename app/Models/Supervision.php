<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupervisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled classroom-observation visit. The current system has no instrument or
 * results yet — this is purely the scheduling record ("status Terjadwal" in the
 * original scope, which is simply the fact that the row exists; there is no other
 * status to track).
 */
#[Fillable(['teacher_id', 'subject_id', 'supervisor_id', 'date', 'location', 'created_by'])]
class Supervision extends Model
{
    /** @use HasFactory<SupervisionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $supervision): void {
            $supervision->created_by ??= auth()->id();
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
     * Get the teacher being supervised.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subject being observed.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the person conducting the supervision.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
