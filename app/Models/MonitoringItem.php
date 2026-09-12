<?php

namespace App\Models;

use App\Enums\ClassMonitoringGroup;
use App\Enums\ClassMonitoringItemStatus;
use Database\Factories\MonitoringItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a monitoring visit's checklist — see {@see ClassMonitoringGroup}
 * for the standard 28-item template this is seeded from.
 */
#[Fillable(['monitoring_result_id', 'group', 'label', 'status'])]
class MonitoringItem extends Model
{
    /** @use HasFactory<MonitoringItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => ClassMonitoringGroup::class,
            'status' => ClassMonitoringItemStatus::class,
        ];
    }

    /**
     * Get the result this checklist item belongs to.
     *
     * @return BelongsTo<MonitoringResult, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(MonitoringResult::class, 'monitoring_result_id');
    }
}
