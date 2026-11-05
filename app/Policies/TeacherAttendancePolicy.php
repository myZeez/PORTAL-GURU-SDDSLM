<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Governs the recap resource. Curriculum management and the principal read it. A teacher
 * fills their own day once through the separate "Absensi Saya" page; filling a missing day
 * for someone else, correcting a saved one, or removing one that was filled in wrongly is
 * reserved for whoever manages attendance (Waka Kurikulum, and Super Admin as the fallback)
 * and always leaves an entry in the attendance history with a reason.
 */
class TeacherAttendancePolicy extends OperationalRecordPolicy
{
    public function create(User $user): bool
    {
        return $user->canManageAttendance();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->canManageAttendance();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->canCorrectDailyRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canCorrectDailyRecords();
    }
}
