<?php

namespace App\Enums;

use App\Models\CalendarDay;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The status of one calendar day, and the rules it drives for attendance, journals,
 * SIMPATI and class monitoring. `Efektif` is never stored: it is the default for any
 * weekday with no row in `calendar_days`. The rest are exceptions an administrator marks.
 *
 * @see CalendarDay::statusFor()
 */
enum CalendarDayStatus: string implements HasColor, HasLabel
{
    case Efektif = 'efektif';
    case Kegiatan = 'kegiatan';
    case KegiatanKhusus = 'kegiatan_khusus';
    case NonEfektif = 'non_efektif';
    case Libur = 'libur';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efektif => 'Efektif',
            self::Kegiatan => 'Kegiatan',
            self::KegiatanKhusus => 'Kegiatan Khusus',
            self::NonEfektif => 'Non-Efektif',
            self::Libur => 'Libur',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Efektif => 'success',
            self::Kegiatan, self::KegiatanKhusus => 'info',
            self::NonEfektif => 'gray',
            self::Libur => 'danger',
        };
    }

    /**
     * Statuses an administrator can mark a date with. Efektif is excluded: it's what a
     * date already is when nothing overrides it.
     *
     * @return list<self>
     */
    public static function exceptions(): array
    {
        return [self::Kegiatan, self::KegiatanKhusus, self::NonEfektif, self::Libur];
    }

    /**
     * Whether teachers must fill attendance on a day with this status.
     */
    public function requiresAttendance(): bool
    {
        return in_array($this, [self::Efektif, self::Kegiatan, self::KegiatanKhusus], true);
    }

    /**
     * Whether teachers must fill their teaching journal on a day with this status.
     */
    public function requiresJournal(): bool
    {
        return $this === self::Efektif;
    }

    /**
     * Whether SIMPATI must be filled on a day with this status.
     */
    public function requiresSimpati(): bool
    {
        return $this !== self::Libur;
    }

    /**
     * Whether class administration monitoring may be scheduled on a day with this status.
     */
    public function allowsMonitoring(): bool
    {
        return ! in_array($this, [self::NonEfektif, self::Libur], true);
    }
}
