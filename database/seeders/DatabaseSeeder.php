<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the school's master data.
     */
    public function run(): void
    {
        $this->call([
            TeacherSeeder::class,
            ClassroomSeeder::class,
            SubjectSeeder::class,
            TimeSlotSeeder::class,
            DailyRoutineSeeder::class,
            SemesterSeeder::class,
        ]);
    }
}
