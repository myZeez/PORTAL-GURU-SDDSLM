<?php

namespace App\Policies;

use App\Models\MonitoringResult;
use App\Models\User;

/**
 * Both Waka Kurikulum and Admin Kurikulum may conduct a monitoring visit ("periksa") —
 * unlike scheduling one, this administrative power is not restricted to Waka Kurikulum.
 */
class MonitoringResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canReviewMonitoring();
    }

    public function view(User $user, MonitoringResult $monitoringResult): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isCurriculumManager();
    }

    public function update(User $user, MonitoringResult $monitoringResult): bool
    {
        return $user->isCurriculumManager();
    }

    public function delete(User $user, MonitoringResult $monitoringResult): bool
    {
        return $user->isCurriculumManager();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isCurriculumManager();
    }

    public function restore(User $user, MonitoringResult $monitoringResult): bool
    {
        return false;
    }

    public function forceDelete(User $user, MonitoringResult $monitoringResult): bool
    {
        return false;
    }
}
