<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Some modules (Manajemen Tugas: tasks and additional duties) are managed exclusively by
 * administrators — unlike master data, not even the principal gets read access, since
 * these are internal admin-to-teacher tools rather than something requiring oversight.
 */
abstract class AdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
