<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A teacher's attendance status for one calendar day. Cuti also carries a leave period
 * on the record (`leave_starts_on`/`leave_ends_on`); the other statuses only cover the day.
 */
enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Hadir = 'hadir';
    case PulangCepat = 'pulang_cepat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Alpa = 'alpa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::PulangCepat => 'Pulang Awal',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::Alpa => 'Tanpa Keterangan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::PulangCepat => 'warning',
            self::Izin => 'info',
            self::Sakit => 'warning',
            self::Cuti => 'gray',
            self::Alpa => 'danger',
        };
    }

    /**
     * Attendance credit for one effective school day. Official holidays never reach
     * this calculation because they are excluded by TeacherAttendance::isFillableDate().
     */
    public function attendanceWeight(): int
    {
        return match ($this) {
            self::Hadir => 100,
            self::PulangCepat => 75,
            self::Izin, self::Sakit => 50,
            self::Cuti, self::Alpa => 0,
        };
    }
}
