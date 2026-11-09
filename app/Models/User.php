<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Education;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Concerns\HasAuditTrail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'name', 'position', 'email', 'password', 'role', 'is_active', 'must_change_password',
    'photo_path', 'nik', 'nuptk', 'birthplace', 'birthdate', 'gender', 'address', 'phone', 'last_education',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasAuditTrail, HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'birthdate' => 'date',
            'gender' => Gender::class,
            'last_education' => Education::class,
        ];
    }

    /**
     * A short description of the account, used as the heading of its change history.
     */
    public function auditLabel(): string
    {
        return $this->name;
    }

    /**
     * The account details worth tracing, by readable name. The password is never included.
     *
     * @return array<string, string>
     */
    public function auditSnapshot(): array
    {
        return [
            'Kode' => (string) $this->code,
            'Nama' => $this->name,
            'Jabatan' => (string) $this->position,
            'Email' => $this->email,
            'Peran' => $this->role?->getLabel() ?? 'Guru',
            'Status' => $this->is_active ? 'Aktif' : 'Tidak aktif',
        ];
    }

    /**
     * Only active staff can sign in; deactivated accounts keep their history.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * The photo shown in every Filament avatar circle (topbar, user menu, etc.) — falls
     * back to Filament's default initials avatar when no photo has been uploaded.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->photo_path || ! Storage::disk('public')->exists($this->photo_path)) {
            return null;
        }

        return route('avatars.show', ['user' => $this, 'v' => $this->updated_at?->timestamp]);
    }

    /**
     * The one or two letters shown when no photo is available, e.g. "KF" for
     * "Kiki Fauziah, S.Si., M.Pd., Gr.".
     *
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => self::initialsFor($this->name));
    }

    /**
     * Build initials from a full name, ignoring academic degrees after the first comma and
     * honorifics such as "Dr." or "H.".
     */
    public static function initialsFor(string $name): string
    {
        $words = collect(preg_split('/\s+/', trim(Str::before($name, ','))) ?: [])
            ->filter(fn (string $word): bool => $word !== '' && ! preg_match('/^(dr|drs|dra|hj?|ir|prof)\.?$/i', $word))
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $word) ?? '', 0, 1)));

        return $words->join('') ?: '?';
    }

    /**
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine whether the user is trusted to make exceptional corrections to records.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    /**
     * Determine whether the user manages curriculum operations and related master data.
     */
    public function isCurriculumManager(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(...Role::curriculumManagers());
    }

    /**
     * Determine whether the user is the principal (Kepala Sekolah), who has read-only access.
     */
    public function isPrincipal(): bool
    {
        return $this->hasRole(Role::KepalaSekolah);
    }

    /**
     * Determine whether the user coordinates extracurriculars (Koordinator Ekskul), who
     * manages that module alongside administrators.
     */
    public function isEkskulCoordinator(): bool
    {
        return $this->hasRole(Role::KoordinatorEkskul);
    }

    /**
     * Determine whether the user is responsible for facilities and PID reservations.
     */
    public function isFacilitiesManager(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaSarpras);
    }

    /**
     * Determine whether the user receives the school-wide dashboard briefing.
     *
     * Facilities and extracurricular coordinators use the teacher dashboard. Their
     * additional responsibilities do not grant access to attendance, journal, or
     * workload information belonging to every teacher.
     */
    public function isSchoolLeader(): bool
    {
        return $this->isPrincipal()
            || $this->isSuperAdmin()
            || $this->isCurriculumManager();
    }

    /**
     * Determine whether the user may review all outing-class submissions.
     */
    public function canReviewOutings(): bool
    {
        return $this->isPrincipal() || $this->isCurriculumManager();
    }

    /**
     * Determine whether the user may review all PID reservations.
     */
    public function canReviewPidReservations(): bool
    {
        return $this->isPrincipal() || $this->isFacilitiesManager();
    }

    /**
     * Determine whether the user decides on outing-class requests: Waka Kurikulum, or
     * Super Admin as the exceptional fallback.
     */
    public function canApproveOutings(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user decides on PID reservations.
     */
    public function canApprovePidReservations(): bool
    {
        return $this->isFacilitiesManager();
    }

    /**
     * Active users who decide on outing-class requests.
     *
     * @return EloquentCollection<int, self>
     */
    public static function outingApprovers(): EloquentCollection
    {
        return static::query()->where('is_active', true)->get()
            ->filter(fn (self $user): bool => $user->canApproveOutings())
            ->values();
    }

    /**
     * Active users who decide on PID reservations.
     *
     * @return EloquentCollection<int, self>
     */
    public static function pidReservationApprovers(): EloquentCollection
    {
        return static::query()->where('is_active', true)->get()
            ->filter(fn (self $user): bool => $user->canApprovePidReservations())
            ->values();
    }

    /**
     * Active teaching staff: everyone who can sign in and is not the principal or the
     * technical Super Admin account. Used wherever work is addressed to "all teachers".
     *
     * @return EloquentCollection<int, self>
     */
    public static function activeTeachers(): EloquentCollection
    {
        return static::query()->where('is_active', true)->orderBy('name')->get()
            ->reject(fn (self $user): bool => $user->isPrincipal() || $user->isSuperAdmin())
            ->values();
    }

    /**
     * Determine whether the user monitors which classes and teachers have carried out the
     * summative assessment: Waka Kurikulum and Admin Kurikulum, with Super Admin as the
     * fallback. Correcting what a teacher entered is for {@see self::canCorrectDailyRecords()}.
     */
    public function canMonitorAssessments(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum, Role::AdminKurikulum);
    }

    /**
     * Determine whether the user sets the kokurikuler schedule: Waka Kurikulum, with Super
     * Admin as the fallback. Admin Kurikulum does not.
     */
    public function canManageCocurriculars(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user is the homeroom teacher (wali kelas) of a class. The
     * assistant (pendamping) is deliberately not one.
     */
    public function isHomeroomTeacher(): bool
    {
        return $this->homeroomClassroom()->exists();
    }

    /**
     * Whether the kokurikuler menus are for this user at all: whoever sets the schedule,
     * and the wali kelas who fill it in. Nobody else sees them.
     */
    public function canAccessCocurriculars(): bool
    {
        return $this->canManageCocurriculars() || $this->isHomeroomTeacher();
    }

    /**
     * Determine whether the user may remove or correct daily records that others filed
     * (substitutions, journals, attendance): Waka Kurikulum, with Super Admin as the
     * fallback. Everything done this way leaves a trace with a reason.
     */
    public function canCorrectDailyRecords(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user manages other teachers' attendance: corrects records,
     * fills missing days on a teacher's behalf, and reopens a closed date.
     */
    public function canManageAttendance(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user manages teacher performance: sets the weights and target,
     * saves each period, and records findings and follow-ups. Waka Kurikulum, with Super
     * Admin as the fallback.
     */
    public function canManagePerformance(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user may see every teacher's performance (the principal reads
     * it for monitoring). Everyone else sees only their own.
     */
    public function canReviewPerformance(): bool
    {
        return $this->isPrincipal() || $this->canManagePerformance();
    }

    /**
     * Determine whether the user may see SIMPATI completion for every class.
     */
    public function canMonitorSimpati(): bool
    {
        return $this->isPrincipal() || $this->isCurriculumManager() || $this->isEkskulCoordinator();
    }

    /**
     * Determine whether the user may tick the SIMPATI checklist of any class, not only the
     * class they assist.
     */
    public function canManageAllSimpati(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(Role::WakaKurikulum);
    }

    /**
     * Determine whether the user may manage extracurricular programs and records.
     */
    public function canManageExtracurriculars(): bool
    {
        return $this->isCurriculumManager() || $this->isEkskulCoordinator();
    }

    /**
     * Determine whether the user may review monitoring data across all teachers.
     */
    public function canReviewMonitoring(): bool
    {
        return $this->isPrincipal() || $this->isCurriculumManager();
    }

    /**
     * Get the classroom this user is the homeroom teacher (wali kelas) of.
     *
     * @return HasOne<Classroom, $this>
     */
    public function homeroomClassroom(): HasOne
    {
        return $this->hasOne(Classroom::class, 'homeroom_teacher_id');
    }

    /**
     * Get the classroom this user assists as pendamping wali kelas.
     *
     * @return HasOne<Classroom, $this>
     */
    public function assistedClassroom(): HasOne
    {
        return $this->hasOne(Classroom::class, 'assistant_teacher_id');
    }
}
