<?php

namespace Database\Seeders;

use App\Enums\Term;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * Open the first semester. SiPeka starts attendance on 13 July 2026, the first day of 2026/2027.
     */
    public function run(): void
    {
        Semester::firstOrCreate(
            ['academic_year' => '2026/2027', 'term' => Term::Ganjil],
            ['starts_on' => '2026-07-13', 'is_active' => true],
        );
    }
}
