<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterDataPolicy
{
    /**
     * Account and role changes can grant system-wide access, so they are reserved for
     * Super Admin rather than ordinary curriculum management.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Super Admin can remove accounts, but never their own.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model);
    }
}
