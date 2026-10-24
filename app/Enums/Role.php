<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * School responsibilities that grant extra access. Homeroom (wali kelas) and assistant
 * (pendamping) duties are not roles: they come from the classroom a teacher is assigned to.
 */
enum Role: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case KepalaSekolah = 'kepala_sekolah';
    case WakaKurikulum = 'waka_kurikulum';
    case AdminKurikulum = 'admin_kurikulum';
    case WakaSarpras = 'waka_sarpras';
    case KoordinatorEkskul = 'koordinator_ekskul';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::KepalaSekolah => 'Kepala Sekolah',
            self::WakaKurikulum => 'Waka Kurikulum',
            self::AdminKurikulum => 'Admin Kurikulum',
            self::WakaSarpras => 'Waka Sarpras',
            self::KoordinatorEkskul => 'Koordinator Ekskul',
        };
    }

    /**
     * Roles responsible for curriculum operations and related master data.
     *
     * @return list<self>
     */
    public static function curriculumManagers(): array
    {
        return [self::WakaKurikulum, self::AdminKurikulum];
    }
}
