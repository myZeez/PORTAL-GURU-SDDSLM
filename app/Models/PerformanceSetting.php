<?php

namespace App\Models;

use App\Enums\PerformanceIndicator;
use Database\Factories\PerformanceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The school's agreed way of reading teacher performance: how much each indicator weighs
 * in the combined score, the target percentage an indicator should reach, and the cut-offs
 * for each performance label. There is a single row. Until the weights are set and scoring
 * is switched on, no combined score is shown; until every cut-off is set, no label is.
 */
#[Fillable(['is_scoring_enabled', 'weights', 'target_percentage', 'thresholds', 'updated_by'])]
class PerformanceSetting extends Model
{
    /** @use HasFactory<PerformanceSettingFactory> */
    use HasFactory;

    public const DEFAULT_TARGET = 80;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_scoring_enabled' => 'boolean',
            'weights' => 'array',
            'thresholds' => 'array',
            'target_percentage' => 'integer',
        ];
    }

    /**
     * The one settings row, created on first use with scoring switched off.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'is_scoring_enabled' => false,
            'target_percentage' => self::DEFAULT_TARGET,
        ]);
    }

    /**
     * Weight (in percent) of each indicator, zero for any that was never given one.
     *
     * @return array<string, int>
     */
    public function weightsByIndicator(): array
    {
        return collect(PerformanceIndicator::cases())
            ->mapWithKeys(fn (PerformanceIndicator $indicator): array => [$indicator->value => (int) ($this->weights[$indicator->value] ?? 0)])
            ->all();
    }

    /**
     * Whether a combined score can be shown: switched on, and the weights total 100%.
     */
    public function canScore(): bool
    {
        return $this->is_scoring_enabled && array_sum($this->weightsByIndicator()) === 100;
    }

    /**
     * The performance label for a score, or null while the school has not agreed on every
     * cut-off yet.
     */
    public function labelFor(?int $score): ?string
    {
        $thresholds = $this->thresholds ?? [];

        if ($score === null || ! $this->canScore()) {
            return null;
        }

        foreach (['excellent', 'good', 'fair'] as $key) {
            if (! isset($thresholds[$key]) || $thresholds[$key] === '') {
                return null;
            }
        }

        return match (true) {
            $score >= (int) $thresholds['excellent'] => 'Sangat Baik',
            $score >= (int) $thresholds['good'] => 'Baik',
            $score >= (int) $thresholds['fair'] => 'Cukup',
            default => 'Perlu Pembinaan',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
