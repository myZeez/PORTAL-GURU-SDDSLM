<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Subjects as listed in SiPeka: [counts toward teaching load (JP), taught to several
     * classes at once on Kamis — see Schedule::isConflictExempt()].
     *
     * @var array<string, array{0: bool, 1: bool}>
     */
    private const SUBJECTS = [
        'Matematika' => [true, false],
        'Seni Rupa' => [true, false],
        'Seni Tari' => [true, false],
        'Seni Musik' => [true, false],
        'Seni Teater' => [true, false],
        'Bahasa Indonesia' => [true, false],
        'Pendidikan Pancasila' => [true, false],
        'Bahasa Arab' => [true, false],
        'Bahasa Inggris' => [true, false],
        'IPAS' => [true, false],
        'PJOK' => [true, true],
        'BK' => [true, false],
        'PAI & BP' => [true, false],
        'Informatika' => [true, false],
        'TKA' => [true, false],
        "Ummi/Al-Qur'an" => [true, false],
        'Penguatan Hafalan' => [false, false],
        'PRAMUKA' => [false, true],
    ];

    /**
     * Create the subjects taught at the school.
     */
    public function run(): void
    {
        foreach (self::SUBJECTS as $name => [$countsTowardTeachingLoad, $allowsConcurrentScheduling]) {
            Subject::firstOrCreate(['name' => $name], [
                'counts_toward_teaching_load' => $countsTowardTeachingLoad,
                'allows_concurrent_scheduling' => $allowsConcurrentScheduling,
            ]);
        }
    }
}
