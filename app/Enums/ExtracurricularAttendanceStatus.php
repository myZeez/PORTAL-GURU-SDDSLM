<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExtracurricularAttendanceStatus: string implements HasColor, HasLabel
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Digantikan = 'digantikan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Digantikan => 'Digantikan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::Izin => 'info',
            self::Sakit => 'warning',
            self::Digantikan => 'gray',
        };
    }
}
