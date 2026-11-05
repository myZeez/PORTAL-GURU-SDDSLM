<?php

namespace App\Policies;

use App\Models\CocurricularSchedule;
use App\Models\User;

/**
 * Waka Kurikulum (and Super Admin) set the kokurikuler schedule and its target classrooms;
 * the wali kelas see it to know which day concerns their class. Nobody else has the menu.
 */
class CocurricularSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessCocurriculars();
    }

    public function view(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return $user->canAccessCocurriculars();
    }

    public function create(User $user): bool
    {
        return $user->canManageCocurriculars();
    }

    public function update(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return $user->canManageCocurriculars();
    }

    public function delete(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return $user->canManageCocurriculars();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageCocurriculars();
    }

    public function restore(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return false;
    }

    public function forceDelete(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return false;
    }
}
