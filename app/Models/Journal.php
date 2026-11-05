<?php

namespace App\Models;

use App\Enums\CalendarDayStatus;
use App\Enums\SchoolDay;
use App\Models\Concerns\HasAuditTrail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\JournalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'date', 'schedule_id', 'teacher_id', 'topic', 'pages', 'resource_url', 'description',
    'students_present', 'students_permitted', 'students_sick', 'students_absent', 'created_by',
])]
class Journal extends Model
{
    use HasAuditTrail;

    /** @use HasFactory<JournalFactory> */
    use HasFactory;

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
        $this->loadMissing('schedule.classroom', 'schedule.subject', 'schedule.timeSlot', 'teacher');

        return [
            'Tanggal' => $this->date->translatedFormat('d F Y'),
            'Jadwal' => "{$this->schedule->classroom->label} — {$this->schedule->subject->name} ({$this->schedule->timeSlot->label})",
            'Guru' => $this->teacher?->name ?? '—',
            'Materi' => (string) $this->topic,
            'Siswa hadir' => (string) $this->students_present,
            'Siswa izin' => (string) $this->students_permitted,
            'Siswa sakit' => (string) $this->students_sick,
            'Siswa tanpa keterangan' => (string) $this->students_absent,
        ];
    }

    /**
     * Record who submitted it, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $journal): void {
            $journal->created_by ??= auth()->id();
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
            'students_present' => 'integer',
            'students_permitted' => 'integer',
            'students_sick' => 'integer',
            'students_absent' => 'integer',
        ];
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
     * Get the schedule slot this journal entry covers. Carries the classroom, subject,
     * time slot and day of week, so none of them are duplicated on this record.
     *
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Get the teacher who actually taught this session (the substitute, if one covered).
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the user who submitted this journal entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether teachers are expected to fill a journal on the given date at all: unlike
     * attendance, only a fully Efektif day requires one (Kegiatan/Kegiatan Khusus don't).
     */
    public static function isFillableDate(Carbon $date): bool
    {
        return static::isFillableOn($date, CalendarDay::statusFor($date));
    }

    /**
     * The same rule as {@see self::isFillableDate()} for a date whose calendar status is
     * already known, so callers going through many dates need not query each one.
     */
    public static function isFillableOn(CarbonInterface $date, CalendarDayStatus $status): bool
    {
        return SchoolDay::tryFrom($date->dayOfWeekIso) !== null && $status->requiresJournal();
    }
}
