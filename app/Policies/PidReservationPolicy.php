<?php

namespace App\Policies;

use App\Models\PidReservation;
use App\Models\User;

/**
 * Everyone except the principal can submit their own PID reservation ("Input"); only
 * administrators approve, edit, or remove reservations — the project scope's "setujui"
 * capability (a PIN in the original app, replaced here by the normal role-based
 * authorization already used throughout the rebuild). The principal reads every
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
        return $user->isAdministrator();
    }

    public function delete(User $user, PidReservation $pidReservation): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
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
