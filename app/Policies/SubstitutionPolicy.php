<?php

namespace App\Policies;

use App\Models\Substitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Governs the recap resource and the edit and delete buttons of "Penggantian Saya".
 * Curriculum management and the principal read substitutions. A substitution can be
 * corrected or removed by Waka Kurikulum (and Super Admin) at any time, and by the teacher
 * who filed it only while it is still ahead of them — see {@see Substitution::canBeChangedBy()}.
 * Filing one stays on the "Penggantian Saya" page; the recap itself only takes entries
 * from Super Admin.
 */
class SubstitutionPolicy extends OperationalRecordPolicy
{
    public function update(User $user, Model $model): bool
    {
        return $model instanceof Substitution && $model->canBeChangedBy($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Substitution && $model->canBeChangedBy($user);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canCorrectDailyRecords();
    }
}
