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
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::Izin => 'info',
            self::Sakit => 'warning',
            self::Cuti => 'gray',
        };
    }
}
