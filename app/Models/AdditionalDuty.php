<?php

namespace App\Models;

use App\Enums\DutyType;
use Database\Factories\AdditionalDutyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teacher_id', 'title', 'type', 'jp', 'created_by'])]
class AdditionalDuty extends Model
{
    /** @use HasFactory<AdditionalDutyFactory> */
    use HasFactory;

    /**
     * Record who assigned it, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $duty): void {
            $duty->created_by ??= auth()->id();
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
            'type' => DutyType::class,
            'jp' => 'integer',
        ];
    }

    /**
     * Get the teacher who holds this duty.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the administrator who recorded this duty.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
