<?php

namespace App\Policies;

/**
 * Governs the admin-facing resource only: administrators manage every substitution, the
 * principal reads it (per the project scope, the principal has read-only access to
 * substitution history — never edit/delete, to keep the record trustworthy). Ordinary
 * teachers never touch this resource — they submit their own through the separate
 * "Penggantian Saya" page, which authorizes itself directly rather than through this policy.
 */
class SubstitutionPolicy extends MasterDataPolicy {}
