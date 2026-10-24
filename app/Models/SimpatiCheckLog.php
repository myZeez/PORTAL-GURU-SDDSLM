<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SimpatiCheckLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One tick or un-tick of a class's SIMPATI checklist, kept for audit. Append-only: the
 * history cannot be edited or deleted through the application.
 */
#[Fillable(['classroom_id', 'date', 'actor_id', 'was_filled', 'is_filled'])]
class SimpatiCheckLog extends Model
{
    /** @use HasFactory<SimpatiCheckLogFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('SIMPATI history cannot be edited.');
        });

        static::deleting(function (): never {
            throw new LogicException('SIMPATI history cannot be deleted.');
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
            'was_filled' => 'boolean',
            'is_filled' => 'boolean',
        ];
    }

    /**
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
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
