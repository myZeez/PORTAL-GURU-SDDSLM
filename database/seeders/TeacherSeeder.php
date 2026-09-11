<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeacherSeeder extends Seeder
{
    /**
     * Staff of SD Islam Darussalam as listed in SiPeka: [code, name, position, roles].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: list<Role>}>
     */
    private const TEACHERS = [
        ['JJ', 'Jamatul Solihin, S.Pd.', 'Kepala Satuan Pendidikan', [Role::KepalaSekolah]],
        ['KN', 'Kiki Fauziah, S.Si., M.Pd., Gr.', 'Wakil Kepala Bidang Kurikulum', [Role::WakaKurikulum]],
        ['IN', 'Ika Noor Aien Rizky, M.Pd., Gr.', 'Wakil Kepala Bidang Hubungan Masyarakat', []],
        ['MK', 'Mika Okta Rahmadani, S.Pd', 'Wakil Kepala Bidang Kesiswaan', [Role::KoordinatorEkskul]],
        ['JW', 'Julian Wahyu, S.Pd., Gr.', 'Wakil Kepala Bidang Sarana dan Prasarana', [Role::WakaSarpras]],
        ['AA', 'Ara Aulia Nada, S.Pd., Gr.', 'Wali Kelas I A', []],
        ['TI', 'Trisna Indari, S.Pd.I., Gr.', 'Pendamping Wali Kelas I A', []],
        ['PW', 'Poppy Wahyu Rosida, M.Pd', 'Wali Kelas I B', []],
        ['BW', 'Bella Wulan Sari, S.Pd', 'Pendamping Wali Kelas I B', []],
        ['NK', 'Noviani Kurniawati, S.Pd., Gr.', 'Wali Kelas I C', []],
        ['SW', 'Santi Widiasari, S.Pd., Gr.', 'Pendamping Wali Kelas I C', []],
        ['FB', 'Febby Febrina Ambar Mahardhika, S.Pd., Gr.', 'Wali Kelas II A', [Role::AdminKurikulum]],
        ['ML', 'Monalisa, S.Pd', 'Pendamping Wali Kelas II A', []],
        ['NH', 'Nurhalifah, S.Pd., Gr.', 'Wali Kelas II B', []],
        ['HH', 'Herlina Hasanah, S.Pd', 'Pendamping Wali Kelas II B', []],
        ['DP', 'Dewi Puspitasari, S.Pd., Gr.', 'Wali Kelas II C', []],
        ['BH', 'Bahrina, S.Pd', 'Pendamping Wali Kelas II C', []],
        ['RK', 'Rina Khairunnisa, S.Pd.I., Gr.', 'Wali Kelas III A', []],
        ['NC', 'Noor Ashyah Cici Oktavia, S.Pd', 'Pendamping Wali Kelas III A', []],
        ['MR', 'Miftahul Rizqiah, S.Pd., Gr.', 'Wali Kelas III B', []],
        ['FM', 'Fitria Mayang Sari, S.Pd., Gr.', 'Pendamping Wali Kelas III B', []],
        ['RF', 'Riafany Febrianty, S.Pd., Gr.', 'Wali Kelas IV A', []],
        ['AT', 'Atiah, M.Pd', 'Pendamping Wali Kelas IV A', []],
        ['AC', 'Andi Cindy Rahmadani Efendi, M.Pd., Gr.', 'Wali Kelas IV B', []],
        ['ED', 'Ega Dyah Pratiwi, S.Pd., Gr.', 'Pendamping Wali Kelas IV B', []],
        ['SL', 'Muhammad Saleh, S.Pd., Gr.', 'Wali Kelas V A', []],
        ['RJ', 'Raudatul Jannah, S.Pd', 'Pendamping Wali Kelas V A', []],
        ['MM', 'Mira Maulanda, S.Pd., Gr.', 'Wali Kelas V B', []],
        ['MI', 'Muhammad Imron, S.Pd', 'Pendamping Wali Kelas V B', []],
        ['SH', 'Silva Hibbati, S.Pd., Gr.', 'Wali Kelas VI A', []],
        ['PL', 'Puji Lestari, S.Pd., Gr.', 'Pendamping Wali Kelas VI A', []],
        ['FN', 'Fitri Fuji Ningrum, S.Pd., Gr.', 'Wali Kelas VI B', []],
        ['MA', "Muhammad A'athaillah, S.Pd", 'Pendamping Wali Kelas VI B', []],
        ['BR', 'Basuki Rahmat, S.Pd', 'Wakil Kepala Bidang Keagamaan', []],
    ];

    /**
     * Create one account per staff member.
     *
     * Accounts start with a placeholder email and a random password, so nobody can sign in
     * until an administrator enters the real email and resets the password. Existing
     * accounts are left untouched, which keeps the seeder safe to re-run.
     */
    public function run(): void
    {
        foreach (self::TEACHERS as [$code, $name, $position, $roles]) {
            User::firstOrCreate(['code' => $code], [
                'name' => $name,
                'position' => $position,
                'roles' => $roles,
                'email' => Str::lower($code).'@portal-guru.test',
                'password' => Str::random(40),
                'is_active' => true,
            ]);
        }
    }
}
