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
        return $user->isAdministrator() || $user->isPrincipal();
    }

    public function view(User $user, MonitoringResult $monitoringResult): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function update(User $user, MonitoringResult $monitoringResult): bool
    {
        return $user->isAdministrator();
    }

    public function delete(User $user, MonitoringResult $monitoringResult): bool
    {
        return $user->isAdministrator();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator();
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
