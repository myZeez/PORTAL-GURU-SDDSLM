<?php

namespace App\Policies;

use App\Models\Cocurricular;
use App\Models\User;

/**
 * Only whoever sets the kokurikuler schedule and the wali kelas have the menu, and they
 * browse the shared feed of submissions. The wali kelas of a class (not the pendamping)
 * submits that class's record; Waka Kurikulum may submit for any class. Once submitted,
 * only Super Admin may correct or remove it.
 */
class CocurricularPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessCocurriculars();
    }

    public function view(User $user, Cocurricular $cocurricular): bool
    {
        return $user->canAccessCocurriculars();
    }

    public function create(User $user): bool
    {
        return $user->canAccessCocurriculars();
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
