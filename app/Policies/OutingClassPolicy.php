<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\OutingClass;
use App\Models\User;

/**
 * Everyone except the principal can submit their own outing request ("Input"); only
 * Waka Kurikulum approves, edits, or removes requests (their own included) — the project
 * scope's "setujui" (approve) capability. Other administrators can still see every
 * request, but only Waka Kurikulum manages them. The principal reads every request.
 */
class OutingClassPolicy
{
    /**
     * Everyone may open the list — the resource's query scopes it to "my own requests"
     * for everyone except administrators and the principal, who see every request.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OutingClass $outingClass): bool
    {
        return $user->isAdministrator() || $user->isPrincipal() || $outingClass->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->isPrincipal();
    }

    public function update(User $user, OutingClass $outingClass): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function delete(User $user, OutingClass $outingClass): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function restore(User $user, OutingClass $outingClass): bool
    {
        return false;
    }

    public function forceDelete(User $user, OutingClass $outingClass): bool
    {
        return false;
    }
}
