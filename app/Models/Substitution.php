<?php

namespace App\Models;

use App\Filament\Pages\SubstitutionSelf;
use App\Models\Concerns\HasAuditTrail;
use Carbon\Carbon;
use Database\Factories\SubstitutionFactory;
use Filament\Actions\Action;
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
    use HasAuditTrail {
        updateWithAudit as private updateAndLog;
    }

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
     * Whether the user may correct or remove this substitution. Whoever manages the daily
     * records may always; the teacher who filed it may only until the day has passed and
     * nobody has written a journal for that lesson yet, so a finished day stays as it was.
     */
    public function canBeChangedBy(User $user): bool
    {
        if ($user->canCorrectDailyRecords()) {
            return true;
        }

        return $this->created_by !== null
            && $this->created_by === $user->id
            && $this->date->gte(today())
            && ! Journal::query()->where('schedule_id', $this->schedule_id)->whereDate('date', $this->date->toDateString())->exists();
    }

    /**
     * Change the substitution and leave a trace; a newly named substitute is told, like
     * the one named when it was first filed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateWithAudit(User $actor, array $attributes, ?string $reason = null): void
    {
        $previousSubstitute = $this->substitute_teacher_id;

        $this->updateAndLog($actor, $attributes, $reason);

        if ($this->substitute_teacher_id !== $previousSubstitute) {
            $this->unsetRelation('substituteTeacher');
            $this->notifySubstituteTeacher();
        }
    }

    public function auditLabel(): string
    {
        $this->loadMissing('schedule.classroom', 'schedule.subject');

        return "{$this->schedule->classroom->label} — {$this->schedule->subject->name} · {$this->date->format('d/m/Y')}";
    }

    /**
     * @return array<string, string>
     */
    public function auditSnapshot(): array
    {
        $this->loadMissing('schedule.classroom', 'schedule.subject', 'schedule.timeSlot', 'schedule.teacher', 'substituteTeacher');

        return [
            'Tanggal' => $this->date->translatedFormat('d F Y'),
            'Jadwal' => "{$this->schedule->classroom->label} — {$this->schedule->subject->name} ({$this->schedule->timeSlot->label})",
            'Guru yang digantikan' => $this->schedule->teacher_name,
            'Guru pengganti' => $this->substituteTeacher->name,
            'Alasan' => (string) $this->reason,
        ];
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
            ->actions([
                Action::make('open')
                    ->label('Lihat penggantian')
                    ->url(SubstitutionSelf::getUrl())
                    ->markAsRead(),
            ])
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
