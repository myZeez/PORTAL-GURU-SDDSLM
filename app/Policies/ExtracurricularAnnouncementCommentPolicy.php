<?php

namespace App\Policies;

use App\Models\ExtracurricularAnnouncementComment;
use App\Models\User;

/**
 * Anyone signed in may join the discussion; only curriculum management, the ekskul
 * coordinator, or the comment's own author may remove it.
 */
class ExtracurricularAnnouncementCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExtracurricularAnnouncementComment $extracurricularAnnouncementComment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ExtracurricularAnnouncementComment $extracurricularAnnouncementComment): bool
    {
        return $extracurricularAnnouncementComment->user_id === $user->id;
    }

    public function delete(User $user, ExtracurricularAnnouncementComment $extracurricularAnnouncementComment): bool
    {
        return $user->canManageExtracurriculars() || $extracurricularAnnouncementComment->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageExtracurriculars();
    }

    public function restore(User $user, ExtracurricularAnnouncementComment $extracurricularAnnouncementComment): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExtracurricularAnnouncementComment $extracurricularAnnouncementComment): bool
    {
        return false;
    }
}
