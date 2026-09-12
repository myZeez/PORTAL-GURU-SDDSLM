<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'link', 'starts_on', 'due_on', 'created_by'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Record who created it, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            $task->created_by ??= auth()->id();
        });
    }

    /**
     * `starts_on` and `due_on` are handled by hand rather than Eloquent's built-in `date`
     * cast: that cast still serializes through the model's datetime format
     * (`Y-m-d H:i:s`) when saving, which a real `DATE` column (MySQL) quietly truncates
     * back to `Y-m-d` but a `TEXT` column (SQLite, used in tests) stores verbatim.
     *
     * @return Attribute<Carbon, string>
     */
    protected function startsOn(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function dueOn(): Attribute
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
            set: fn (Carbon|string|null $value): ?string => filled($value) ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * Get the user who created this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the users this task was assigned to, with their completion state.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['is_completed', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Get the per-assignee completion rows directly.
     *
     * @return HasMany<TaskAssignee, $this>
     */
    public function taskAssignees(): HasMany
    {
        return $this->hasMany(TaskAssignee::class);
    }
}
