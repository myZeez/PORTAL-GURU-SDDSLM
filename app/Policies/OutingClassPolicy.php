<?php

namespace App\Policies;

use App\Models\OutingClass;
use App\Models\User;

/**
 * Everyone except the principal can submit their own outing request ("Input"); only
 * Waka Kurikulum approves, edits, or removes requests (their own included) — the project
 * scope's "setujui" (approve) capability. Admin Kurikulum supports the review, while
 * only Waka Kurikulum manages them. The principal reads every request.
 */
class OutingClassPolicy
{
    /**
     * Everyone may open the list — the resource's query scopes it to "my own requests"
     * for everyone except curriculum management and the principal, who see every request.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OutingClass $outingClass): bool
    {
        return $user->canReviewOutings() || $outingClass->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->isPrincipal();
    }

    public function update(User $user, OutingClass $outingClass): bool
    {
        return $user->canApproveOutings();
    }

    public function delete(User $user, OutingClass $outingClass): bool
    {
        return $user->canApproveOutings();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canApproveOutings();
    }

    public function restore(User $user, OutingClass $outingClass): bool
    {
        return false;
    }

    public function forceDelete(User $user, OutingClass $outingClass): bool
    {
        return false;
    }
}
