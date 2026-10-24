<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PerformancePeriod: string implements HasLabel
{
    case Week = 'week';
    case Month = 'month';
    case Semester = 'semester';
    case Range = 'range';

    public function getLabel(): string
    {
        return match ($this) {
            self::Week => 'Mingguan',
            self::Month => 'Bulanan',
            self::Semester => 'Semester',
            self::Range => 'Rentang tanggal',
        };
    }
}
