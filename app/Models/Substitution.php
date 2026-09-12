<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SubstitutionFactory;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'schedule_id', 'substitute_teacher_id', 'reason', 'created_by'])]
class Substitution extends Model
{
    /** @use HasFactory<SubstitutionFactory> */
    use HasFactory;

    /**
     * Record who submitted it, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $substitution): void {
            $substitution->created_by ??= auth()->id();
        });

        static::created(function (self $substitution): void {
            $substitution->notifySubstituteTeacher();
        });
    }

    /**
     * Notify the substitute teacher that they have been assigned to cover a class.
     */
    private function notifySubstituteTeacher(): void
    {
        $this->loadMissing('schedule.classroom', 'schedule.subject');

        Notification::make()
            ->title('Anda ditugaskan sebagai guru pengganti')
            ->body("{$this->schedule->classroom->label} — {$this->schedule->subject->name} pada {$this->date->translatedFormat('d F Y')}.")
            ->info()
            ->sendToDatabase($this->substituteTeacher);
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
     * Get the schedule slot being substituted. Carries the original teacher, classroom,
     * subject and time slot, so none of them are duplicated on this record.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Get the teacher covering the class.
     *
     * @return BelongsTo<User, $this>
     */
    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }

    /**
     * Get the user who submitted this substitution.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
