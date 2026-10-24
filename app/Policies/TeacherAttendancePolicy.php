<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Governs the recap resource. Curriculum management and the principal read it. A teacher
 * fills their own day once through the separate "Absensi Saya" page; filling a missing day
 * for someone else, or correcting a saved one, is reserved for whoever manages attendance
 * (Waka Kurikulum, and Super Admin as the fallback) and always leaves an audit entry with
 * a reason. Removing a record stays with Super Admin alone.
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
}
