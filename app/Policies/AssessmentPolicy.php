<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Unlike every other module, "Input" here is strictly self-only for every role,
 * including administrators — nobody enters an assessment on someone else's behalf. A
 * submitted assessment becomes historical evidence: Waka Kurikulum (and Super Admin) may
 * still correct or remove one a teacher entered wrongly, which always leaves a trace.
 * The principal, Waka Kurikulum and Admin Kurikulum can read everyone's.
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
        return $user->isSuperAdmin() || $user->isPrincipal() || $user->canMonitorAssessments() || $assessment->teacher_id === $user->id;
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
        return $user->canCorrectDailyRecords();
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->canCorrectDailyRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canCorrectDailyRecords();
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
