-- Revisi klien 24 Oktober 2026: tabel baru dan jejak persetujuan.
-- Impor satu kali lewat phpMyAdmin pada database aplikasi, setelah
-- 2026_10_05_add_early_leave_reason.sql. Struktur sama persis dengan migrasi Laravel.

SET NAMES utf8mb4;

CREATE TABLE `teacher_attendance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_attendance_id` bigint unsigned DEFAULT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `action` varchar(255) NOT NULL,
  `before` json DEFAULT NULL,
  `after` json NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_attendance_logs_teacher_attendance_id_foreign` (`teacher_attendance_id`),
  KEY `teacher_attendance_logs_actor_id_foreign` (`actor_id`),
  KEY `teacher_attendance_logs_teacher_id_date_index` (`teacher_id`,`date`),
  CONSTRAINT `teacher_attendance_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `teacher_attendance_logs_teacher_attendance_id_foreign` FOREIGN KEY (`teacher_attendance_id`) REFERENCES `teacher_attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `teacher_attendance_logs_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `calendar_day_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `before_status` varchar(255) NOT NULL,
  `after_status` varchar(255) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calendar_day_logs_actor_id_foreign` (`actor_id`),
  KEY `calendar_day_logs_date_index` (`date`),
  CONSTRAINT `calendar_day_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `attendance_reopenings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reason` text NOT NULL,
  `opened_by` bigint unsigned NOT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_reopenings_date_unique` (`date`),
  KEY `attendance_reopenings_opened_by_foreign` (`opened_by`),
  KEY `attendance_reopenings_closed_by_foreign` (`closed_by`),
  CONSTRAINT `attendance_reopenings_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_reopenings_opened_by_foreign` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `simpati_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `is_filled` tinyint(1) NOT NULL DEFAULT '0',
  `checked_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `simpati_checks_classroom_id_date_unique` (`classroom_id`,`date`),
  KEY `simpati_checks_checked_by_foreign` (`checked_by`),
  CONSTRAINT `simpati_checks_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `simpati_checks_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `simpati_check_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `was_filled` tinyint(1) NOT NULL,
  `is_filled` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `simpati_check_logs_actor_id_foreign` (`actor_id`),
  KEY `simpati_check_logs_classroom_id_date_index` (`classroom_id`,`date`),
  CONSTRAINT `simpati_check_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `simpati_check_logs_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `performance_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `is_scoring_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `weights` json DEFAULT NULL,
  `target_percentage` tinyint unsigned NOT NULL DEFAULT '80',
  `thresholds` json DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performance_settings_updated_by_foreign` (`updated_by`),
  CONSTRAINT `performance_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `performance_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `period_type` varchar(255) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `indicators` json NOT NULL,
  `jp` json DEFAULT NULL,
  `score` tinyint unsigned DEFAULT NULL,
  `notes` text,
  `saved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `performance_snapshots_teacher_id_period_start_period_end_unique` (`teacher_id`,`period_start`,`period_end`),
  KEY `performance_snapshots_saved_by_foreign` (`saved_by`),
  CONSTRAINT `performance_snapshots_saved_by_foreign` FOREIGN KEY (`saved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `performance_snapshots_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `performance_follow_ups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `indicator` varchar(255) DEFAULT NULL,
  `finding` text NOT NULL,
  `recommendation` text NOT NULL,
  `responsible_id` bigint unsigned DEFAULT NULL,
  `target_date` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performance_follow_ups_responsible_id_foreign` (`responsible_id`),
  KEY `performance_follow_ups_created_by_foreign` (`created_by`),
  KEY `performance_follow_ups_teacher_id_target_date_index` (`teacher_id`,`target_date`),
  CONSTRAINT `performance_follow_ups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `performance_follow_ups_responsible_id_foreign` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `performance_follow_ups_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `outing_classes`
    ADD COLUMN `approved_by` bigint unsigned NULL AFTER `requested_by`,
    ADD COLUMN `approved_at` timestamp NULL AFTER `approved_by`,
    ADD KEY `outing_classes_approved_by_foreign` (`approved_by`),
    ADD CONSTRAINT `outing_classes_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `pid_reservations`
    ADD COLUMN `approved_by` bigint unsigned NULL AFTER `requested_by`,
    ADD COLUMN `approved_at` timestamp NULL AFTER `approved_by`,
    ADD KEY `pid_reservations_approved_by_foreign` (`approved_by`),
    ADD CONSTRAINT `pid_reservations_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Catat migrasi yang sudah terpasang supaya `php artisan migrate` tidak mengulanginya.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`migration`, (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) AS b)
FROM (
    SELECT '2026_10_24_065206_create_teacher_attendance_logs_table' AS `migration`
    UNION ALL SELECT '2026_10_24_065208_create_calendar_day_logs_table'
    UNION ALL SELECT '2026_10_24_065210_create_attendance_reopenings_table'
    UNION ALL SELECT '2026_10_24_070424_create_simpati_checks_table'
    UNION ALL SELECT '2026_10_24_070426_create_simpati_check_logs_table'
    UNION ALL SELECT '2026_10_24_071055_create_performance_settings_table'
    UNION ALL SELECT '2026_10_24_071058_create_performance_snapshots_table'
    UNION ALL SELECT '2026_10_24_071100_create_performance_follow_ups_table'
    UNION ALL SELECT '2026_10_24_173912_add_approval_trail_to_outing_classes_and_pid_reservations'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS x WHERE x.`migration` = m.`migration`);
