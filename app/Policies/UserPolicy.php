<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterDataPolicy
{
    /**
     * Administrators can remove accounts, but never their own.
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $user->isNot($model);
    }
}
