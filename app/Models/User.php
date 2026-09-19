<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Education;
use App\Enums\Gender;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'code', 'name', 'position', 'email', 'password', 'role', 'is_active', 'must_change_password',
    'photo_path', 'nik', 'nuptk', 'birthplace', 'birthdate', 'gender', 'address', 'phone', 'last_education',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine whether the user administers the portal (Waka Kurikulum, Admin Kurikulum, Developer).
     */
    public function isAdministrator(): bool
    {
        return $this->hasRole(...Role::administrators());
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
