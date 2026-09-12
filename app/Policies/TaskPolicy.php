<?php

namespace App\Policies;

/**
 * Governs the admin-facing resource only: administrators manage every task. Not even the
 * principal has read access here (unlike master data) — assignees see their own tasks
 * through the separate "Tugas Saya" page instead, which every signed-in user can open.
 */
class TaskPolicy extends AdminOnlyPolicy {}
