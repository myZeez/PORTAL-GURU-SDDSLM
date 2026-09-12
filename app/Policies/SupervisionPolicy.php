<?php

namespace App\Policies;

/**
 * Governs the admin-facing resource only: administrators schedule every supervision
 * visit, the principal reads them. The teacher being supervised sees their own upcoming
 * visits through the separate "Supervisi Saya" page instead — they never schedule or
 * edit these themselves.
 */
class SupervisionPolicy extends MasterDataPolicy {}
