<?php

namespace App\Models;

use App\Enums\OutingStatus;
use App\Filament\Resources\OutingClasses\OutingClassResource;
use Carbon\Carbon;
use Database\Factories\OutingClassFactory;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['classroom_id', 'date', 'destination', 'description', 'status', 'requested_by'])]
class OutingClass extends Model
{
    /** @use HasFactory<OutingClassFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $outing): void {
            $outing->requested_by ??= auth()->id();
            $outing->status ??= OutingStatus::Menunggu;
        });

        static::created(function (self $outing): void {
            $outing->notifyApprovers();
        });
    }

    /**
     * Tell everyone who can approve outing classes that a request is waiting for them.
     */
    private function notifyApprovers(): void
    {
        $recipients = User::outingApprovers()->reject(fn (User $user): bool => $user->id === $this->requested_by);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->loadMissing('classroom', 'requestedBy');

        Notification::make()
            ->title('Pengajuan outing class menunggu persetujuan')
            ->body("{$this->classroom->label} — {$this->destination} pada {$this->date->translatedFormat('d F Y')}, diajukan oleh {$this->requestedBy?->name}.")
            ->warning()
            ->actions([
                Action::make('review')
                    ->label('Tinjau pengajuan')
                    ->url(OutingClassResource::getUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OutingStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Approve a pending request and record who approved it and when. The approver and the
     * time are not mass-assignable, so this is the only way they get written.
     */
    public function approve(User $approver): void
    {
        $this->forceFill([
            'status' => OutingStatus::Disetujui,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();
    }

    /**
     * `date` is handled by hand rather than Eloquent's built-in `date` cast: that cast
     * still serializes through the model's datetime format (`Y-m-d H:i:s`) when saving,
     * which a real `DATE` column (MySQL) quietly truncates back to `Y-m-d` but a `TEXT`
     * column (SQLite, used in tests) stores verbatim.
     *
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
     * Get the classroom going on the outing.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the user who submitted this request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved this request.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
