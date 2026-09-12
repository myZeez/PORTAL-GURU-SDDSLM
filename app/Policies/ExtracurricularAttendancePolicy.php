<?php

namespace App\Policies;

use App\Models\ExtracurricularAttendance;
use App\Models\User;

/**
 * Every guru pendamping logs their own attendance ("Input" applies even to
 * administrators); administrators and the ekskul coordinator manage everyone's.
 */
class ExtracurricularAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->isPrincipal();
    }

    public function update(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator() || $extracurricularAttendance->teacher_id === $user->id;
    }

    public function delete(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator() || $extracurricularAttendance->teacher_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function restore(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return false;
    }
}
