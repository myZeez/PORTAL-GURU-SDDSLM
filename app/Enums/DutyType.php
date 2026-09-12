<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How an additional duty (a non-teaching responsibility that still counts toward a
 * teacher's workload, e.g. Wali Kelas or Kepala Perpustakaan) is classified.
 */
enum DutyType: string implements HasLabel
{
    case Utama = 'utama';
    case Tambahan = 'tambahan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Utama => 'Utama',
            self::Tambahan => 'Tambahan',
        };
    }
}
