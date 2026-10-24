<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Completed operational records are historical evidence. Curriculum management and the
 * principal may inspect them, but only Super Admin may correct or remove a record.
 */
abstract class OperationalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isCurriculumManager() || $user->isPrincipal();
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
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
