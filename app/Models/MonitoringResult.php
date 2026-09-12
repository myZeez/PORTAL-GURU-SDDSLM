<?php

namespace App\Models;

use App\Enums\ClassMonitoringGroup;
use App\Enums\ClassMonitoringItemStatus;
use App\Enums\ClassMonitoringResultStatus;
use Carbon\Carbon;
use Database\Factories\MonitoringResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The 28-item checklist filled in during one classroom administration monitoring visit.
 * A visit's score and status are computed from its {@see MonitoringItem} rows — see
 * {@see self::computeScore()} — never set directly.
 */
#[Fillable(['monitoring_schedule_id', 'score', 'status', 'notes', 'recommendation', 'due_date', 'created_by'])]
class MonitoringResult extends Model
{
    /** @use HasFactory<MonitoringResultFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $result): void {
            $result->created_by ??= auth()->id();
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
            'status' => ClassMonitoringResultStatus::class,
        ];
    }

    /**
     * @return Attribute<?Carbon, Carbon|string|null>
     */
    protected function dueDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string|null $value): ?string => filled($value) ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * Get the schedule this checklist belongs to.
     *
     * @return BelongsTo<MonitoringSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MonitoringSchedule::class, 'monitoring_schedule_id');
    }

    /**
     * Get this result's 28 checklist items.
     *
     * @return HasMany<MonitoringItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MonitoringItem::class);
    }

    /**
     * Get the user who conducted this visit.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Start a monitoring visit: create the result and seed its 28 checklist items from
     * the standard template, with every item unanswered.
     */
    public static function startFor(MonitoringSchedule $schedule): self
    {
        $result = self::create(['monitoring_schedule_id' => $schedule->id]);

        $result->items()->createMany(
            collect(ClassMonitoringGroup::checklist())->map(fn (array $entry): array => [
                'group' => $entry['group'],
                'label' => $entry['label'],
            ])->all()
        );

        return $result;
    }

    /**
     * Recompute this visit's score and status from its checklist items, per the school's
     * scoring rule: score = Lengkap ÷ relevant items (excluding Tidak Relevan); ≥ 90% is
     * Sangat Lengkap, 75–89% is Perlu Perhatian, and anything lower — or any single item
     * marked Belum Ada — is Perlu Tindak Lanjut.
     */
    public function computeScore(): void
    {
        $items = $this->items()->get();

        $relevant = $items->filter(fn (MonitoringItem $item): bool => $item->status !== null && $item->status !== ClassMonitoringItemStatus::TidakRelevan);

        $score = $relevant->isEmpty()
            ? 0
            : (int) round($relevant->filter(fn (MonitoringItem $item): bool => $item->status === ClassMonitoringItemStatus::Lengkap)->count() / $relevant->count() * 100);

        $hasMissingItem = $relevant->contains(fn (MonitoringItem $item): bool => $item->status === ClassMonitoringItemStatus::BelumAda);

        $status = match (true) {
            $hasMissingItem || $score < 75 => ClassMonitoringResultStatus::PerluTindakLanjut,
            $score >= 90 => ClassMonitoringResultStatus::SangatLengkap,
            default => ClassMonitoringResultStatus::PerluPerhatian,
        };

        $this->update(['score' => $score, 'status' => $status]);
    }
}
