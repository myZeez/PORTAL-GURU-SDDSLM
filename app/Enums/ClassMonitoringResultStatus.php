<?php

namespace App\Enums;

use App\Models\MonitoringResult;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Computed from a monitoring visit's checklist — see {@see MonitoringResult::computeScore()}.
 */
enum ClassMonitoringResultStatus: string implements HasColor, HasLabel
{
    case SangatLengkap = 'sangat_lengkap';
    case PerluPerhatian = 'perlu_perhatian';
    case PerluTindakLanjut = 'perlu_tindak_lanjut';

    public function getLabel(): string
    {
        return match ($this) {
            self::SangatLengkap => 'Sangat Lengkap',
            self::PerluPerhatian => 'Perlu Perhatian',
            self::PerluTindakLanjut => 'Perlu Tindak Lanjut',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SangatLengkap => 'success',
            self::PerluPerhatian => 'warning',
            self::PerluTindakLanjut => 'danger',
        };
    }
}
