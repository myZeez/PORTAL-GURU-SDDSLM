<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\MonitoringSchedule;
use App\Models\User;

/**
 * Administrators and the principal can see every scheduled visit; only Waka Kurikulum
 * schedules one — Admin Kurikulum has every other administrative power in this app
 * except this one, per the school's decision.
 */
class MonitoringSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isPrincipal();
    }

    public function view(User $user, MonitoringSchedule $monitoringSchedule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function update(User $user, MonitoringSchedule $monitoringSchedule): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function delete(User $user, MonitoringSchedule $monitoringSchedule): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(Role::WakaKurikulum);
    }

    public function restore(User $user, MonitoringSchedule $monitoringSchedule): bool
    {
        return false;
    }

    public function forceDelete(User $user, MonitoringSchedule $monitoringSchedule): bool
    {
        return false;
    }
}
