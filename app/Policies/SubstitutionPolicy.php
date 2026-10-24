<?php

namespace App\Policies;

/**
 * Governs the recap resource. Curriculum management and the principal read substitutions,
 * while only Super Admin may correct a submitted substitution. Teachers submit their own
 * through the separate "Penggantian Saya" page.
 */
class SubstitutionPolicy extends OperationalRecordPolicy {}
