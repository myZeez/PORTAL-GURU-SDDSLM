<?php

namespace App\Policies;

use App\Models\User;

/**
 * The attendance audit trail is read-only for everyone. Whoever manages attendance, the
 * rest of curriculum management and the principal may read it, so a correction can always
 * be traced back to who made it and why.
 */
class TeacherAttendanceLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageAttendance() || $user->isCurriculumManager() || $user->isPrincipal();
    }
}
