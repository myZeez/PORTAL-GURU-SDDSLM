<?php

namespace App\Filament\Resources\AdditionalDuties\Pages;

use App\Filament\Resources\AdditionalDuties\AdditionalDutyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdditionalDuties extends ManageRecords
{
    protected static string $resource = AdditionalDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
