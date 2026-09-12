<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The 8 Dimensi Profil Lulusan (graduate-profile dimensions) an activity can be tagged
 * against, per Permendikdasmen No. 12 Tahun 2024.
 */
enum GraduateDimension: string implements HasLabel
{
    case KeimananDanKetakwaan = 'keimanan_dan_ketakwaan';
    case Kewargaan = 'kewargaan';
    case PenalaranKritis = 'penalaran_kritis';
    case Kreativitas = 'kreativitas';
    case Kolaborasi = 'kolaborasi';
    case Kemandirian = 'kemandirian';
    case Kesehatan = 'kesehatan';
    case Komunikasi = 'komunikasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::KeimananDanKetakwaan => 'Keimanan dan Ketakwaan terhadap Tuhan YME',
            self::Kewargaan => 'Kewargaan',
            self::PenalaranKritis => 'Penalaran Kritis',
            self::Kreativitas => 'Kreativitas',
            self::Kolaborasi => 'Kolaborasi',
            self::Kemandirian => 'Kemandirian',
            self::Kesehatan => 'Kesehatan',
            self::Komunikasi => 'Komunikasi',
        };
    }
}
