<?php

namespace Database\Seeders;

use App\Enums\SchoolDay;
use App\Models\DailyRoutine;
use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class DailyRoutineSeeder extends Seeder
{
    /**
     * Create the whole-school routines shown in the weekly schedule matrix.
     */
    public function run(): void
    {
        $timeSlotIds = TimeSlot::query()
            ->get()
            ->mapWithKeys(fn (TimeSlot $timeSlot): array => [substr($timeSlot->starts_at, 0, 5) => $timeSlot->id]);

        foreach ($this->routinesByDay() as $day => $routines) {
            foreach ($routines as [$startsAt, $label]) {
                DailyRoutine::firstOrCreate([
                    'day_of_week' => $day,
                    'time_slot_id' => $timeSlotIds->get($startsAt),
                ], [
                    'label' => $label,
                ]);
            }
        }
    }

    /**
     * Routines per school day as listed in SiPeka: [slot start time, label].
     *
     * @return array<int, list<array{0: string, 1: string}>>
     */
    private function routinesByDay(): array
    {
        $midweek = [
            ['06:00', 'Sarapan Makanan Bergizi Gratis'],
            ['06:40', 'Salat Dhuha'],
            ['07:00', 'Pelita Hidup'],
            ['09:30', 'Istirahat'],
            ['11:00', 'ISOMA'],
            ['13:00', 'Power Nap'],
            ['13:30', 'Pengisian SIMPATI'],
            ['15:30', 'Ekstrakurikuler'],
        ];

        return [
            SchoolDay::Senin->value => [
                ['06:00', 'Sarapan Makanan Bergizi Gratis'],
                ['06:40', 'Upacara Bendera'],
                ['07:00', 'Pelita Hidup'],
                ['09:30', 'Istirahat'],
                ['11:00', 'ISOMA'],
                ['13:00', 'Power Nap'],
                ['13:30', 'Pengisian SIMPATI'],
            ],
            SchoolDay::Selasa->value => $midweek,
            SchoolDay::Rabu->value => $midweek,
            SchoolDay::Kamis->value => $midweek,
            SchoolDay::Jumat->value => [
                ['06:00', 'Sarapan Makanan Bergizi Gratis'],
                ['06:40', 'Sekolah Sehat & Adiwiyata'],
                ['07:00', 'Pelita Hidup'],
                ['07:30', 'Kokurikuler / Career Day / Pesona Manasai / Sehari Adiwiyata'],
                ['08:00', 'Kokurikuler / Career Day / Pesona Manasai / Sehari Adiwiyata'],
                ['09:30', 'Istirahat'],
                ['11:00', 'ISOMA'],
                ['13:00', 'Pengisian SIMPATI'],
                ['13:30', 'Persiapan Pulang'],
            ],
        ];
    }
}
