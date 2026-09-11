<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data is managed by administrators (Waka Kurikulum, Admin Kurikulum, Developer) and
 * can be read by the principal. Every ability Filament may ask about is declared explicitly,
 * so nothing is allowed by accident.
 */
abstract class MasterDataPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isPrincipal();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Master data is never soft-deleted, so there is nothing to restore.
     */
    public function restore(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Master data is never soft-deleted, so there is nothing to restore.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    /**
     * Master data is never soft-deleted, so there is nothing to force delete.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Master data is never soft-deleted, so there is nothing to force delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can reorder the models.
     */
    public function reorder(User $user): bool
    {
        return false;
    }
}
