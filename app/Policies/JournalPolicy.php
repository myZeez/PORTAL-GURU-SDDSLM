<?php

namespace App\Policies;

/**
 * Governs the recap resource. Curriculum management and the principal read journals,
 * while only Super Admin may correct a completed journal. Teachers submit their own
 * journal once through the separate "Jurnal Saya" page.
 */
class JournalPolicy extends OperationalRecordPolicy {}
