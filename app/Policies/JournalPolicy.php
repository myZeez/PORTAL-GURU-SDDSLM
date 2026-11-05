<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Governs the recap resource. Curriculum management and the principal read journals,
 * while only Super Admin may correct a completed journal. Waka Kurikulum may remove one
 * that was filled in wrongly, with a reason that is kept in the change history. Teachers
 * submit their own journal once through the separate "Jurnal Saya" page.
 */
class JournalPolicy extends OperationalRecordPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $user->canCorrectDailyRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canCorrectDailyRecords();
    }
}
