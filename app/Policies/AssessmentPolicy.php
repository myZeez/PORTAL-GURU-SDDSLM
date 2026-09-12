<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Unlike every other module, "Input" here is strictly self-only for every role,
 * including administrators — there is no "Kelola" tier that manages everyone else's
 * records. The principal gets read-only access to everyone's, per the project scope.
 */
class AssessmentPolicy
{
    /**
     * Everyone may open the list — the resource's query scopes it to "my own" for
     * everyone except the principal, who sees everyone's (read-only).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $user->isPrincipal() || $assessment->teacher_id === $user->id;
    }

    /**
     * The principal never records assessments themselves.
     */
    public function create(User $user): bool
    {
        return ! $user->isPrincipal();
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $assessment->teacher_id === $user->id;
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $assessment->teacher_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Assessment $assessment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Assessment $assessment): bool
    {
        return false;
    }
}
