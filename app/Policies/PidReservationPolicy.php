<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PidReservation;
use App\Models\User;

/**
 * Everyone except the principal can submit their own PID reservation ("Input"); only
 * Waka Sarpras approves, edits, or removes reservations — the project scope's "setujui"
 * capability (a PIN in the original app, replaced here by the normal role-based
 * authorization already used throughout the rebuild). Other administrators can still see
 * every reservation, but only Waka Sarpras manages them. The principal reads every
 * reservation.
 */
class PidReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PidReservation $pidReservation): bool
    {
        return $user->isAdministrator() || $user->isPrincipal() || $pidReservation->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->isPrincipal();
    }

    public function update(User $user, PidReservation $pidReservation): bool
    {
        return $user->hasRole(Role::WakaSarpras);
    }

    public function delete(User $user, PidReservation $pidReservation): bool
    {
        return $user->hasRole(Role::WakaSarpras);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(Role::WakaSarpras);
    }

    public function restore(User $user, PidReservation $pidReservation): bool
    {
        return false;
    }

    public function forceDelete(User $user, PidReservation $pidReservation): bool
    {
        return false;
    }
}
