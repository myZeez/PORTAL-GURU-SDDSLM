<?php

namespace App\Policies;

use App\Models\Curriculum;
use App\Models\User;

/**
 * Unlike master data, "Lihat" here means literally everyone — not just the principal —
 * since curriculum documents are reference material every teacher should be able to
 * browse. Only administrators manage the library itself.
 */
class CurriculumPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Curriculum $curriculum): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, Curriculum $curriculum): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, Curriculum $curriculum): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Curriculum $curriculum): bool
    {
        return false;
    }

    public function forceDelete(User $user, Curriculum $curriculum): bool
    {
        return false;
    }
}
