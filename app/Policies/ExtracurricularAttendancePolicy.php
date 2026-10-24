<?php

namespace App\Policies;

use App\Models\ExtracurricularAttendance;
use App\Models\User;

/**
 * Every guru pendamping logs their own attendance. Once submitted, it becomes a
 * historical record: extracurricular management can review it, while only Super Admin
 * may correct or remove it.
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
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ExtracurricularAttendance $extracurricularAttendance): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
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
