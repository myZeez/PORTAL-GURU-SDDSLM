<?php

namespace App\Policies;

use App\Models\Extracurricular;
use App\Models\User;

/**
 * Everyone can browse the extracurricular schedule; only administrators and the
 * extracurricular coordinator (Koordinator Ekskul) manage it.
 */
class ExtracurricularPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Extracurricular $extracurricular): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function update(User $user, Extracurricular $extracurricular): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function delete(User $user, Extracurricular $extracurricular): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function restore(User $user, Extracurricular $extracurricular): bool
    {
        return false;
    }

    public function forceDelete(User $user, Extracurricular $extracurricular): bool
    {
        return false;
    }
}
