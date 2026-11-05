<?php

namespace App\Models;

use Database\Factories\RecordAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in the trail of changes made to a daily record after it was saved: who changed
 * or removed it, when, what it said before and after, and why. Entries are append-only, so
 * the trail cannot be rewritten through the application, and they outlive the record they
 * describe.
 */
#[Fillable(['subject_type', 'subject_id', 'action', 'label', 'actor_id', 'before', 'after', 'reason'])]
class RecordAudit extends Model
{
    /** @use HasFactory<RecordAuditFactory> */
    use HasFactory;

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_BULK_DELETED = 'bulk_deleted';

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('The change history cannot be edited.');
        });

        static::deleting(function (): never {
            throw new LogicException('The change history cannot be deleted.');
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
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * Write one entry about a record.
     *
     * @param  array<string, string>|null  $before
     * @param  array<string, string>|null  $after
     */
    public static function record(Model $subject, string $action, string $label, ?array $before, ?array $after, ?string $reason, User $actor): self
    {
        return static::query()->create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'label' => $label,
            'actor_id' => $actor->id,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }

    /**
     * Get the user who made the change.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Only the fields whose value differs between before and after, as [field => [before, after]].
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function changes(): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($this->before ?? []), ...array_keys($this->after ?? [])]) as $field) {
            $old = $this->before[$field] ?? null;
            $new = $this->after[$field] ?? null;

            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }
}
