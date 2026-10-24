<?php

namespace App\Models;

use App\Enums\PerformancePeriod;
use Carbon\Carbon;
use Database\Factories\PerformanceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's performance for one evaluation period, saved so progress can be compared
 * over time. Saving the same period again refreshes it (after source data is corrected);
 * the figures are copied here rather than recomputed, so a saved period stays as it was
 * when it was reviewed.
 */
#[Fillable(['teacher_id', 'period_type', 'period_start', 'period_end', 'indicators', 'jp', 'score', 'notes', 'saved_by'])]
class PerformanceSnapshot extends Model
{
    /** @use HasFactory<PerformanceSnapshotFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_type' => PerformancePeriod::class,
            'indicators' => 'array',
            'jp' => 'array',
            'score' => 'integer',
        ];
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function periodStart(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function periodEnd(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    private static function dateOnly(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string $value): string => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * Get the period as people read it, e.g. "1 Okt – 31 Okt 2026".
     *
     * @return Attribute<string, never>
     */
    protected function periodLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->period_start->translatedFormat('d M Y').' – '.$this->period_end->translatedFormat('d M Y'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
