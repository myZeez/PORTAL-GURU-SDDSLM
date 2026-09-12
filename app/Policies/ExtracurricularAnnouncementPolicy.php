<?php

namespace App\Policies;

use App\Models\ExtracurricularAnnouncement;
use App\Models\User;

/**
 * Everyone reads the announcement wall; only administrators, the ekskul coordinator,
 * or the announcement's own author may post, edit, or remove one.
 */
class ExtracurricularAnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function update(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return $this->manages($user, $extracurricularAnnouncement);
    }

    public function delete(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return $this->manages($user, $extracurricularAnnouncement);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator();
    }

    public function restore(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return false;
    }

    private function manages(User $user, ExtracurricularAnnouncement $extracurricularAnnouncement): bool
    {
        return $user->isAdministrator() || $user->isEkskulCoordinator() || $extracurricularAnnouncement->created_by === $user->id;
    }
}
