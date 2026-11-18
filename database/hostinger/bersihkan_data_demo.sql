-- Membersihkan data demo sebelum dipakai sungguhan. SEKALI JALAN, TIDAK BISA DIBATALKAN:
-- export database dulu sebagai cadangan.
--
-- DIHAPUS : absensi, jurnal, penggantian, penilaian, supervisi, tugas, outing, PID, kokurikuler,
--           absensi dan pengumuman ekskul, monitoring kelas, SIMPATI, performance, notifikasi,
--           riwayat perubahan, libur contoh Agustus 2026, dan kelas contoh DEMO-01 beserta jadwalnya.
-- DIJAGA  : akun guru dan staf, rombel asli, mata pelajaran, jam pelajaran, rutinitas harian,
--           semester, jadwal pelajaran asli, pengaturan performance, dan tanggal absensi yang
--           sudah dibuka kembali (attendance_reopenings).
--
-- Memakai DELETE (bukan TRUNCATE) dari tabel turunan ke induk, jadi aman dijalankan di phpMyAdmin
-- dengan pemeriksaan kunci asing tetap menyala. Aman juga diulang kalau terhenti di tengah jalan.

-- 1. Kelas contoh dan jadwalnya (jurnal dan penggantian yang terkait ikut terhapus otomatis).
DELETE FROM `schedules` WHERE `classroom_id` IN (SELECT `id` FROM (SELECT `id` FROM `classrooms` WHERE `code` = 'DEMO-01') AS demo);
DELETE FROM `classrooms` WHERE `code` = 'DEMO-01';

-- 2. Libur dan kegiatan contoh pada kalender.
DELETE FROM `calendar_days` WHERE `date` IN ('2026-08-10', '2026-08-12', '2026-08-13', '2026-08-17', '2026-08-18')
  AND `notes` IN ('Lomba Peringatan HUT RI ke-81', 'Penilaian Tengah Semester Ganjil', 'Hari Kemerdekaan Republik Indonesia', 'Cuti Bersama');

-- 3. Catatan harian dan isian contoh.
DELETE FROM `teacher_attendance_logs`;
DELETE FROM `teacher_attendances`;
DELETE FROM `calendar_day_logs`;
DELETE FROM `journals`;
DELETE FROM `substitutions`;
DELETE FROM `assessments`;
DELETE FROM `supervisions`;
DELETE FROM `task_assignees`;
DELETE FROM `tasks`;
DELETE FROM `additional_duties`;
DELETE FROM `outing_classes`;
DELETE FROM `pid_reservations`;
DELETE FROM `cocurriculars`;
DELETE FROM `extracurricular_attendances`;
DELETE FROM `extracurricular_announcement_comments`;
DELETE FROM `extracurricular_announcements`;
DELETE FROM `monitoring_results`;
DELETE FROM `monitoring_schedules`;
DELETE FROM `simpati_check_logs`;
DELETE FROM `simpati_checks`;
DELETE FROM `performance_follow_ups`;
DELETE FROM `performance_snapshots`;
DELETE FROM `record_audits`;
DELETE FROM `notifications`;

-- 4. OPSIONAL. Buang tanda "--" di depan baris di bawah hanya kalau dokumen kurikulum, jadwal
--    kokurikuler, dan jadwal ekskul di server juga masih contoh. Kalau sudah ada yang asli, biarkan.
-- DELETE FROM `cocurricular_schedule_classroom`;
-- DELETE FROM `cocurricular_schedules`;
-- DELETE FROM `extracurricular_teacher`;
-- DELETE FROM `extracurriculars`;
-- DELETE FROM `curricula`;
