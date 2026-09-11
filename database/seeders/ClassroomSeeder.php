<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassroomSeeder extends Seeder
{
    /**
     * Classes as listed in SiPeka: [code, name, grade, homeroom teacher code, assistant teacher code].
     *
     * @var list<array{0: string, 1: string, 2: int, 3: string, 4: string}>
     */
    private const CLASSROOMS = [
        ['I-A', 'Ibnu Sina', 1, 'AA', 'TI'],
        ['I-B', 'Hasan Al-Banna', 1, 'PW', 'BW'],
        ['I-C', 'Salahudin Al Ayyubi', 1, 'NK', 'SW'],
        ['II-A', 'Sinan Al-Battani', 2, 'FB', 'ML'],
        ['II-B', 'Al-Khawarizmi', 2, 'NH', 'HH'],
        ['II-C', 'Umar Khayyam', 2, 'DP', 'BH'],
        ['III-A', 'Abu Nasir Al-Farabi', 3, 'RK', 'NC'],
        ['III-B', 'Ibnu Al-Haytsam', 3, 'MR', 'FM'],
        ['IV-A', 'Ahmad Al-Biruni', 4, 'RF', 'AT'],
        ['IV-B', 'Tsabit bin Qurrah', 4, 'AC', 'ED'],
        ['V-A', 'Abbas bin Firnas', 5, 'SL', 'RJ'],
        ['V-B', 'Al-Kindi', 5, 'MM', 'MI'],
        ['VI-A', 'Ibnu Khaldun', 6, 'SH', 'PL'],
        ['VI-B', 'Jabbir bin Hayyan', 6, 'FN', 'MA'],
    ];

    /**
     * Create the 14 classes and link their homeroom and assistant teachers.
     */
    public function run(): void
    {
        $teacherIds = User::query()->whereNotNull('code')->pluck('id', 'code');

        foreach (self::CLASSROOMS as [$code, $name, $grade, $homeroomTeacherCode, $assistantTeacherCode]) {
            Classroom::firstOrCreate(['code' => $code], [
                'name' => $name,
                'grade' => $grade,
                'homeroom_teacher_id' => $teacherIds->get($homeroomTeacherCode),
                'assistant_teacher_id' => $teacherIds->get($assistantTeacherCode),
            ]);
        }
    }
}
