<?php

namespace App\Models\Concerns;

use App\Models\RecordAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * For daily records that may be corrected or removed after they were saved, but only with a
 * trace: {@see self::updateWithAudit()} and {@see self::deleteWithAudit()} change the
 * record and write the {@see RecordAudit} entry in one transaction.
 */
trait HasAuditTrail
{
    /**
     * A short description of the record, used as the heading of its trail entries.
     */
    abstract public function auditLabel(): string;

    /**
     * What the record says right now, by readable field name, for comparing before and after.
     *
     * @return array<string, string>
     */
    abstract public function auditSnapshot(): array;

    /**
     * Get the trail entries written about this record.
     *
     * @return MorphMany<RecordAudit, $this>
     */
    public function auditTrail(): MorphMany
    {
        return $this->morphMany(RecordAudit::class, 'subject');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateWithAudit(User $actor, array $attributes, ?string $reason = null): void
    {
        DB::transaction(function () use ($actor, $attributes, $reason): void {
            $before = $this->auditSnapshot();

            $this->update($attributes);

            $fresh = $this->fresh();

            RecordAudit::record($this, RecordAudit::ACTION_UPDATED, $fresh->auditLabel(), $before, $fresh->auditSnapshot(), $reason, $actor);
        });
    }

    public function deleteWithAudit(User $actor, string $reason): bool
    {
        return DB::transaction(function () use ($actor, $reason): bool {
            $label = $this->auditLabel();
            $before = $this->auditSnapshot();

            if (! $this->delete()) {
                return false;
            }

            RecordAudit::record($this, RecordAudit::ACTION_DELETED, $label, $before, null, $reason, $actor);

            return true;
        });
    }
}
