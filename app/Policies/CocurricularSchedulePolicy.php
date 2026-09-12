<?php

namespace App\Policies;

use App\Models\CocurricularSchedule;
use App\Models\User;

/**
 * Everyone can see which kokurikuler days are scheduled and their target classrooms —
 * only administrators set the schedule itself.
 */
class CocurricularSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, CocurricularSchedule $cocurricularSchedule): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
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
