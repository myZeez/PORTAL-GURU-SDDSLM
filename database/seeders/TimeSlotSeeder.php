<?php

namespace Database\Seeders;

use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    /**
     * The school's 19 time slots as listed in SiPeka: [starts at, ends at].
     *
     * @var list<array{0: string, 1: string}>
     */
    private const SLOTS = [
        ['06:00', '06:40'],
        ['06:40', '07:00'],
        ['07:00', '07:30'],
        ['07:30', '08:00'],
        ['08:00', '08:30'],
        ['08:30', '09:00'],
        ['09:00', '09:30'],
        ['09:30', '10:00'],
        ['10:00', '10:30'],
        ['10:30', '11:00'],
        ['11:00', '12:00'],
        ['12:00', '12:30'],
        ['12:30', '13:00'],
        ['13:00', '13:30'],
        ['13:30', '13:50'],
        ['13:50', '14:20'],
        ['14:20', '14:50'],
        ['14:50', '15:30'],
        ['15:30', '16:30'],
    ];

    /**
     * Create the time slots of a school day.
     */
    public function run(): void
    {
        foreach (self::SLOTS as [$startsAt, $endsAt]) {
            TimeSlot::firstOrCreate(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
        }
    }
}
