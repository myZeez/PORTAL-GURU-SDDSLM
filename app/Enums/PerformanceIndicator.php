<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The measurable parts of a teacher's performance. Each is a percentage worked out only
 * from records that really exist in the system; an indicator that does not apply to a
 * teacher (for example SIMPATI for someone who is not a pendamping) is "Tidak Berlaku",
 * never zero.
 */
enum PerformanceIndicator: string implements HasLabel
{
    case Attendance = 'attendance';
    case Journal = 'journal';
    case Tasks = 'tasks';
    case Simpati = 'simpati';
    case ClassAdministration = 'class_administration';
    case FollowUp = 'follow_up';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attendance => 'Kehadiran',
            self::Journal => 'Jurnal mengajar',
            self::Tasks => 'Penyelesaian tugas',
            self::Simpati => 'SIMPATI',
            self::ClassAdministration => 'Administrasi kelas',
            self::FollowUp => 'Tindak lanjut supervisi',
        };
    }

    /**
     * How the percentage is worked out, in the school's own words.
     */
    public function getBasis(): string
    {
        return match ($this) {
            self::Attendance => 'Hari hadir dibandingkan hari kerja yang wajib dihadiri, dengan memperhitungkan status izin, sakit, cuti, dan hari libur sesuai kebijakan sekolah.',
            self::Journal => 'Jurnal terisi dibandingkan jurnal yang wajib dibuat berdasarkan jadwal mengajar.',
            self::Tasks => 'Tugas selesai dibandingkan tugas yang wajib diselesaikan pada periode evaluasi.',
            self::Simpati => 'Kelengkapan pengisian pada kelas dan tanggal yang menjadi tanggung jawab guru.',
            self::ClassAdministration => 'Kelengkapan checklist administrasi sesuai tugas wali kelas atau pendamping.',
            self::FollowUp => 'Penyelesaian tindak lanjut yang ditugaskan kepada guru.',
        };
    }

    /**
     * Shown when the indicator does not apply, so the reason is clear instead of a blank.
     */
    public function getNotApplicableReason(): string
    {
        return match ($this) {
            self::Attendance => 'Tidak ada hari wajib absensi pada periode ini.',
            self::Journal => 'Tidak ada jadwal mengajar yang mewajibkan jurnal pada periode ini.',
            self::Tasks => 'Tidak ada tugas yang jatuh tempo pada periode ini.',
            self::Simpati => 'Guru ini bukan pendamping wali kelas, atau tidak ada hari wajib SIMPATI.',
            self::ClassAdministration => 'Guru ini bukan wali kelas atau pendamping, atau belum ada kunjungan monitoring pada periode ini.',
            self::FollowUp => 'Tidak ada tindak lanjut yang ditugaskan dan jatuh tempo pada periode ini.',
        };
    }
}
