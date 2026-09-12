<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['code', 'name', 'position', 'email', 'password', 'roles', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'roles' => '[]',
        'is_active' => true,
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
            'roles' => AsEnumCollection::of(Role::class),
            'is_active' => 'boolean',
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
     * Determine whether the user holds any of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return (bool) $this->roles?->contains(fn (Role $role): bool => in_array($role, $roles, true));
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
