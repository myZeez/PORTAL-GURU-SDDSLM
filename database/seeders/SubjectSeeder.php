<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Subjects as listed in SiPeka, mapped to whether they count toward teaching load (JP).
     *
     * @var array<string, bool>
     */
    private const SUBJECTS = [
        'Matematika' => true,
        'Seni Rupa' => true,
        'Seni Tari' => true,
        'Seni Musik' => true,
        'Seni Teater' => true,
        'Bahasa Indonesia' => true,
        'Pendidikan Pancasila' => true,
        'Bahasa Arab' => true,
        'Bahasa Inggris' => true,
        'IPAS' => true,
        'PJOK' => true,
        'BK' => true,
        'PAI & BP' => true,
        'Informatika' => true,
        'TKA' => true,
        "Ummi/Al-Qur'an" => true,
        'Penguatan Hafalan' => false,
        'PRAMUKA' => false,
    ];

    /**
     * Create the subjects taught at the school.
     */
    public function run(): void
    {
        foreach (self::SUBJECTS as $name => $countsTowardTeachingLoad) {
            Subject::firstOrCreate(['name' => $name], [
                'counts_toward_teaching_load' => $countsTowardTeachingLoad,
            ]);
        }
    }
}
