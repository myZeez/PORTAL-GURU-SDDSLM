<?php

namespace App\Filament\Resources\AdditionalDuties\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Resources\AdditionalDuties\AdditionalDutyResource;
use App\Models\AdditionalDuty;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdditionalDuties extends ManageRecords
{
    protected static string $resource = AdditionalDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(AdditionalDuty::class, 'Riwayat penghapusan tugas tambahan'),
            CreateAction::make(),
        ];
    }
}
