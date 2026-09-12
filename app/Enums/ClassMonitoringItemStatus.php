<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClassMonitoringItemStatus: string implements HasColor, HasLabel
{
    case Lengkap = 'lengkap';
    case PerluDiperbaiki = 'perlu_diperbaiki';
    case BelumAda = 'belum_ada';
    case TidakRelevan = 'tidak_relevan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lengkap => 'Lengkap',
            self::PerluDiperbaiki => 'Perlu Diperbaiki',
            self::BelumAda => 'Belum Ada',
            self::TidakRelevan => 'Tidak Relevan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Lengkap => 'success',
            self::PerluDiperbaiki => 'warning',
            self::BelumAda => 'danger',
            self::TidakRelevan => 'gray',
        };
    }
}
