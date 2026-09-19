<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Highest completed level of formal education (pendidikan terakhir).
 */
enum Education: string implements HasLabel
{
    case SMA = 'sma';
    case D3 = 'd3';
    case S1 = 's1';
    case S2 = 's2';
    case S3 = 's3';

    public function getLabel(): string
    {
        return match ($this) {
            self::SMA => 'SMA/SMK',
            self::D3 => 'D3',
            self::S1 => 'S1',
            self::S2 => 'S2',
            self::S3 => 'S3',
        };
    }
}
