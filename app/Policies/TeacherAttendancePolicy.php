<?php

namespace App\Policies;

/**
 * Governs the admin-facing Jadwal Pelajaran-style resource only: administrators manage
 * every teacher's attendance, the principal reads it for the recap. Ordinary teachers
 * never touch this resource — they fill their own day through the separate "Absensi
 * Saya" page, which authorizes itself directly rather than through this policy.
 */
class TeacherAttendancePolicy extends MasterDataPolicy {}
