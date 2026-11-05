-- Revisi klien gelombang kedua (November 2026): Tim UMMI dan riwayat perubahan data harian.
-- Impor satu kali lewat phpMyAdmin pada database aplikasi, setelah 2026_10_24_client_revisions.sql.
-- Struktur sama persis dengan migrasi Laravel.
--
-- Perhatian: jadwal UMMI yang sudah ada tidak lagi memakai guru perorangan. Kolom guru pada
-- jadwal itu dikosongkan dan mapel UMMI tidak dihitung JP, sama seperti jadwal baru.

SET NAMES utf8mb4;

-- 1. Mata pelajaran yang diajar sebuah tim (UMMI) dan jadwal tanpa guru perorangan.
ALTER TABLE `subjects`
    ADD COLUMN `team_name` varchar(255) NULL AFTER `allows_concurrent_scheduling`;

ALTER TABLE `schedules`
    MODIFY `teacher_id` bigint unsigned NULL;

UPDATE `subjects`
SET `team_name` = 'Tim UMMI', `counts_toward_teaching_load` = 0
WHERE `name` LIKE 'Ummi%';

UPDATE `schedules`
SET `teacher_id` = NULL
WHERE `subject_id` IN (SELECT `id` FROM (SELECT `id` FROM `subjects` WHERE `name` LIKE 'Ummi%') AS ummi);

-- 2. Riwayat perubahan dan penghapusan data harian (penggantian guru, jurnal).
CREATE TABLE `record_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(255) NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `before` json DEFAULT NULL,
  `after` json DEFAULT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_audits_actor_id_foreign` (`actor_id`),
  KEY `record_audits_subject_type_created_at_index` (`subject_type`,`created_at`),
  KEY `record_audits_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  CONSTRAINT `record_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catat migrasi yang sudah terpasang supaya `php artisan migrate` tidak mengulanginya.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT m.`migration`, (SELECT COALESCE(MAX(b.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) AS b)
FROM (
    SELECT '2026_11_03_083419_add_team_teaching_to_subjects_and_schedules' AS `migration`
    UNION ALL SELECT '2026_11_03_083937_create_record_audits_table'
) AS m
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `migration` FROM `migrations`) AS x WHERE x.`migration` = m.`migration`);
