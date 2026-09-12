<?php

namespace App\Policies;

/**
 * Governs the admin-facing resource only: administrators manage every additional duty
 * (which feeds into a teacher's workload alongside their teaching JP). Not even the
 * principal has read access here — this is internal admin bookkeeping, surfaced to each
 * teacher later through their own Profil page rather than through this resource.
 */
class AdditionalDutyPolicy extends AdminOnlyPolicy {}
