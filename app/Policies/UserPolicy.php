<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterDataPolicy
{
    /**
     * Super Admin and Waka Kurikulum manage teacher and staff accounts.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(Role::WakaKurikulum);
    }

    /**
     * Waka Kurikulum may edit any account, including login email, password, role and the
     * active switch, except Super Admin accounts, which only a Super Admin can touch.
     */
    public function update(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole(Role::WakaKurikulum) && ! $model->isSuperAdmin();
    }

    /**
     * Accounts are deactivated, not deleted, so history stays intact. Only Super Admin can
     * remove an account for good, and never their own.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model);
    }
}
