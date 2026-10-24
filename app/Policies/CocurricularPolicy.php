<?php

namespace App\Policies;

use App\Models\Cocurricular;
use App\Models\User;

/**
 * Submissions form a shared feed everyone can browse. Curriculum management and the
 * homeroom or assistant teacher (wali/pendamping) may submit a classroom's record.
 * Once submitted, only Super Admin may correct or remove it.
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
        return $user->isCurriculumManager()
            || $user->homeroomClassroom()->exists()
            || $user->assistedClassroom()->exists();
    }

    public function update(User $user, Cocurricular $cocurricular): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Cocurricular $cocurricular): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
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
