<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\MonitoringScheduleFactory;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A scheduled classroom administration inspection visit. Only Waka Kurikulum schedules
 * these; the actual checklist is recorded separately once the visit starts, as a
 * {@see MonitoringResult}.
 */
#[Fillable(['date', 'classroom_id', 'notes', 'created_by'])]
class MonitoringSchedule extends Model
{
    /** @use HasFactory<MonitoringScheduleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            $schedule->created_by ??= auth()->id();
        });

        static::created(function (self $schedule): void {
            $schedule->notifyClassroomTeachers();
        });
    }

    /**
     * Notify the classroom's homeroom and assistant teacher (wali & pendamping) that
     * their class has a monitoring visit coming up.
     */
    private function notifyClassroomTeachers(): void
    {
        $this->loadMissing('classroom');

        $recipients = User::query()
            ->whereIn('id', array_filter([
                $this->classroom->homeroom_teacher_id,
                $this->classroom->assistant_teacher_id,
            ]))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Monitoring administrasi kelas dijadwalkan')
            ->body("Kelas {$this->classroom->label} akan dimonitor pada {$this->date->translatedFormat('d F Y')}.")
            ->warning()
            ->sendToDatabase($recipients);
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
     * Get the classroom being inspected.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get this visit's checklist result, once started.
     *
     * @return HasOne<MonitoringResult, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(MonitoringResult::class);
    }

    /**
     * Get the user who scheduled this visit.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
