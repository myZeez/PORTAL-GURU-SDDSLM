<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OutingStatus: string implements HasColor, HasLabel
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Disetujui => 'Disetujui',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Disetujui => 'success',
        };
    }
}
