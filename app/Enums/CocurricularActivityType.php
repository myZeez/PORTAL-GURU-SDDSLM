<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CocurricularActivityType: string implements HasLabel
{
    case Proyek = 'proyek';
    case Kunjungan = 'kunjungan';
    case AksiSosial = 'aksi_sosial';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proyek => 'Proyek',
            self::Kunjungan => 'Kunjungan',
            self::AksiSosial => 'Aksi Sosial',
            self::Lainnya => 'Lainnya',
        };
    }
}
