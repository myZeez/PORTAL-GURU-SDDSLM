<?php

namespace App\Models;

use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use Carbon\Carbon;
use Database\Factories\PerformanceFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A finding raised about a teacher's performance, with the recommendation, who is
 * responsible, the date it should be done by, and how far along it is. Follow-ups assigned
 * to a teacher feed that teacher's "Tindak lanjut supervisi" indicator.
 */
#[Fillable(['teacher_id', 'indicator', 'finding', 'recommendation', 'responsible_id', 'target_date', 'status', 'completed_at', 'created_by'])]
class PerformanceFollowUp extends Model
{
    /** @use HasFactory<PerformanceFollowUpFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $followUp): void {
            $followUp->created_by ??= auth()->id();
            $followUp->status ??= PerformanceFollowUpStatus::Open;
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
            'indicator' => PerformanceIndicator::class,
            'status' => PerformanceFollowUpStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function targetDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string $value): string => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * Move the follow-up to a new status, stamping when it was completed.
     */
    public function markAs(PerformanceFollowUpStatus $status): void
    {
        $this->update([
            'status' => $status,
            'completed_at' => $status === PerformanceFollowUpStatus::Done ? now() : null,
        ]);
    }

    /**
     * The teacher the finding is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * The person who has to act on it.
     *
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
