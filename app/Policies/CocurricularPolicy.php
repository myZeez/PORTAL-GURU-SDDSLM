<?php

namespace App\Policies;

use App\Models\Cocurricular;
use App\Models\User;

/**
 * Submissions form a shared feed everyone can browse. Only administrators, and the
 * homeroom or assistant teacher (wali/pendamping) of the classroom in question, may
 * submit or edit a classroom's kokurikuler record.
 */
class CocurricularPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cocurricular $cocurricular): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator()
            || $user->homeroomClassroom()->exists()
            || $user->assistedClassroom()->exists();
    }

    public function update(User $user, Cocurricular $cocurricular): bool
    {
        return $user->isAdministrator() || $this->teachesClassroom($user, $cocurricular);
    }

    public function delete(User $user, Cocurricular $cocurricular): bool
    {
        return $user->isAdministrator() || $this->teachesClassroom($user, $cocurricular);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function restore(User $user, Cocurricular $cocurricular): bool
    {
        return false;
    }

    public function forceDelete(User $user, Cocurricular $cocurricular): bool
    {
        return false;
    }

    /**
     * Determine whether the user is the homeroom or assistant teacher (wali/pendamping)
     * of the classroom this submission belongs to.
     */
    private function teachesClassroom(User $user, Cocurricular $cocurricular): bool
    {
        return $cocurricular->classroom->homeroom_teacher_id === $user->id
            || $cocurricular->classroom->assistant_teacher_id === $user->id;
    }
}
