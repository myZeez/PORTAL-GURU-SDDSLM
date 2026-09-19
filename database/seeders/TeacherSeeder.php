<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeacherSeeder extends Seeder
{
    /**
     * Staff of SD Islam Darussalam as listed in SiPeka: [code, name, position, role].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: ?Role}>
     */
    private const TEACHERS = [
        ['JJ', 'Jamatul Solihin, S.Pd.', 'Kepala Satuan Pendidikan', Role::KepalaSekolah],
        ['KN', 'Kiki Fauziah, S.Si., M.Pd., Gr.', 'Wakil Kepala Bidang Kurikulum', Role::WakaKurikulum],
        ['IN', 'Ika Noor Aien Rizky, M.Pd., Gr.', 'Wakil Kepala Bidang Hubungan Masyarakat', null],
        ['MK', 'Mika Okta Rahmadani, S.Pd', 'Wakil Kepala Bidang Kesiswaan', Role::KoordinatorEkskul],
        ['JW', 'Julian Wahyu, S.Pd., Gr.', 'Wakil Kepala Bidang Sarana dan Prasarana', Role::WakaSarpras],
        ['AA', 'Ara Aulia Nada, S.Pd., Gr.', 'Wali Kelas I A', null],
        ['TI', 'Trisna Indari, S.Pd.I., Gr.', 'Pendamping Wali Kelas I A', null],
        ['PW', 'Poppy Wahyu Rosida, M.Pd', 'Wali Kelas I B', null],
        ['BW', 'Bella Wulan Sari, S.Pd', 'Pendamping Wali Kelas I B', null],
        ['NK', 'Noviani Kurniawati, S.Pd., Gr.', 'Wali Kelas I C', null],
        ['SW', 'Santi Widiasari, S.Pd., Gr.', 'Pendamping Wali Kelas I C', null],
        ['FB', 'Febby Febrina Ambar Mahardhika, S.Pd., Gr.', 'Wali Kelas II A', Role::AdminKurikulum],
        ['ML', 'Monalisa, S.Pd', 'Pendamping Wali Kelas II A', null],
        ['NH', 'Nurhalifah, S.Pd., Gr.', 'Wali Kelas II B', null],
        ['HH', 'Herlina Hasanah, S.Pd', 'Pendamping Wali Kelas II B', null],
        ['DP', 'Dewi Puspitasari, S.Pd., Gr.', 'Wali Kelas II C', null],
        ['BH', 'Bahrina, S.Pd', 'Pendamping Wali Kelas II C', null],
        ['RK', 'Rina Khairunnisa, S.Pd.I., Gr.', 'Wali Kelas III A', null],
        ['NC', 'Noor Ashyah Cici Oktavia, S.Pd', 'Pendamping Wali Kelas III A', null],
        ['MR', 'Miftahul Rizqiah, S.Pd., Gr.', 'Wali Kelas III B', null],
        ['FM', 'Fitria Mayang Sari, S.Pd., Gr.', 'Pendamping Wali Kelas III B', null],
        ['RF', 'Riafany Febrianty, S.Pd., Gr.', 'Wali Kelas IV A', null],
        ['AT', 'Atiah, M.Pd', 'Pendamping Wali Kelas IV A', null],
        ['AC', 'Andi Cindy Rahmadani Efendi, M.Pd., Gr.', 'Wali Kelas IV B', null],
        ['ED', 'Ega Dyah Pratiwi, S.Pd., Gr.', 'Pendamping Wali Kelas IV B', null],
        ['SL', 'Muhammad Saleh, S.Pd., Gr.', 'Wali Kelas V A', null],
        ['RJ', 'Raudatul Jannah, S.Pd', 'Pendamping Wali Kelas V A', null],
        ['MM', 'Mira Maulanda, S.Pd., Gr.', 'Wali Kelas V B', null],
        ['MI', 'Muhammad Imron, S.Pd', 'Pendamping Wali Kelas V B', null],
        ['SH', 'Silva Hibbati, S.Pd., Gr.', 'Wali Kelas VI A', null],
        ['PL', 'Puji Lestari, S.Pd., Gr.', 'Pendamping Wali Kelas VI A', null],
        ['FN', 'Fitri Fuji Ningrum, S.Pd., Gr.', 'Wali Kelas VI B', null],
        ['MA', "Muhammad A'athaillah, S.Pd", 'Pendamping Wali Kelas VI B', null],
        ['BR', 'Basuki Rahmat, S.Pd', 'Wakil Kepala Bidang Keagamaan', null],
    ];

    /**
     * Create or sync one account per staff member.
     *
     * Email is derived from the staff member's first name (e.g. "Jamatul Solihin" ->
     * jamatul@guru.com), falling back to "firstname.secondname" when the first name repeats
     * (there are three "Muhammad"s in the roster). Every account shares the same starting
     * password; in production each account is forced to change it on first sign-in.
     */
    public function run(): void
    {
        $usedEmails = [];

        foreach (self::TEACHERS as [$code, $name, $position, $role]) {
            $email = $this->emailFor($name, $usedEmails);
            $usedEmails[] = $email;

            User::updateOrCreate(['code' => $code], [
                'name' => $name,
                'position' => $position,
                'role' => $role,
                'email' => $email,
                'password' => '1234567890',
                'is_active' => true,
                'must_change_password' => app()->isProduction(),
            ]);
        }
    }

    /**
     * Derive a "firstname@guru.com" login email from a staff member's full name, appending
     * the second name when the first name has already been used by an earlier entry.
     *
     * @param  list<string>  $usedEmails
     */
    private function emailFor(string $name, array $usedEmails): string
    {
        $words = preg_split('/\s+/', trim(Str::before($name, ',')));
        $normalize = fn (string $word): string => Str::of($word)->ascii()->lower()->replaceMatches('/[^a-z]/', '')->toString();

        $local = $normalize($words[0]);

        if (in_array("{$local}@guru.com", $usedEmails, true)) {
            $local .= '.'.$normalize($words[1] ?? '');
        }

        return "{$local}@guru.com";
    }
}
