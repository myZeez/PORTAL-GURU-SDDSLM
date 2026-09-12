<?php

namespace App\Policies;

/**
 * Governs the admin-facing resource only: administrators manage every journal entry, the
 * principal reads them. Ordinary teachers never touch this resource — they fill and edit
 * their own through the separate "Jurnal Saya" page, which authorizes itself directly
 * (a teacher may edit or delete only the entries they themselves taught) rather than
 * through this policy.
 */
class JournalPolicy extends MasterDataPolicy {}
