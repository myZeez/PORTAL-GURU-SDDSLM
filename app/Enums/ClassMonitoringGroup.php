<?php

namespace App\Enums;

use App\Models\MonitoringResult;
use Filament\Support\Contracts\HasLabel;

enum ClassMonitoringGroup: string implements HasLabel
{
    case AdministrasiPembelajaran = 'administrasi_pembelajaran';
    case WaliKelas = 'wali_kelas';
    case PesertaDidik = 'peserta_didik';
    case DokumenDigital = 'dokumen_digital';
    case LingkunganKelas = 'lingkungan_kelas';

    public function getLabel(): string
    {
        return match ($this) {
            self::AdministrasiPembelajaran => 'Administrasi Pembelajaran',
            self::WaliKelas => 'Wali Kelas',
            self::PesertaDidik => 'Peserta Didik',
            self::DokumenDigital => 'Dokumen Digital',
            self::LingkunganKelas => 'Lingkungan Kelas',
        };
    }

    /**
     * The standard checklist items for this group. This is placeholder wording — adjust
     * it to match the school's actual monitoring checklist before relying on it for real
     * inspections.
     *
     * @return list<string>
     */
    public function items(): array
    {
        return match ($this) {
            self::AdministrasiPembelajaran => [
                'RPP/Modul Ajar tersedia dan sesuai jadwal',
                'Silabus/Alur Tujuan Pembelajaran (ATP) tersedia',
                'Daftar nilai/rekap penilaian sumatif terisi',
                'Program tahunan dan program semester tersedia',
                'Jurnal mengajar terisi rutin',
                'Kalender pendidikan kelas terpasang',
            ],
            self::WaliKelas => [
                'Buku induk/data siswa kelas lengkap',
                'Papan struktur organisasi kelas terpasang',
                'Buku catatan perkembangan siswa terisi',
                'Rekap kehadiran siswa bulanan tersedia',
                'Komunikasi dengan orang tua terdokumentasi',
            ],
            self::PesertaDidik => [
                'Daftar hadir siswa harian terisi',
                'Data pembinaan siswa (jika ada) terdokumentasi',
                'Portofolio/hasil karya siswa tersimpan rapi',
                'Denah tempat duduk terpasang dan sesuai',
                'Piket kelas berjalan dan terjadwal',
                'Data siswa berkebutuhan khusus (jika ada) tertangani',
            ],
            self::DokumenDigital => [
                'Jurnal digital terisi tepat waktu',
                'Absensi digital terisi tepat waktu',
                'Dokumentasi kegiatan kokurikuler diunggah',
                'Berkas kelas tersimpan cadangan di Drive',
                'Data digital rombel terverifikasi',
            ],
            self::LingkunganKelas => [
                'Kebersihan dan kerapian kelas terjaga',
                'Pajangan hasil karya siswa tertata',
                'Fasilitas kelas (meja, kursi, papan tulis) dalam kondisi baik',
                'Pencahayaan dan ventilasi kelas memadai',
                'Alat kebersihan kelas tersedia dan tertata',
                'Dekorasi kelas mendukung suasana belajar',
            ],
        };
    }

    /**
     * Flatten every group's items into [group, label] pairs, in a stable order — used to
     * seed a fresh {@see MonitoringResult}'s 28 checklist rows.
     *
     * @return list<array{group: self, label: string}>
     */
    public static function checklist(): array
    {
        $flat = [];

        foreach (self::cases() as $group) {
            foreach ($group->items() as $label) {
                $flat[] = ['group' => $group, 'label' => $label];
            }
        }

        return $flat;
    }
}
