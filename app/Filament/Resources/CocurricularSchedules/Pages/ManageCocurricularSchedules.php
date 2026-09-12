<?php

namespace App\Filament\Resources\CocurricularSchedules\Pages;

use App\Filament\Resources\CocurricularSchedules\CocurricularScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCocurricularSchedules extends ManageRecords
{
    protected static string $resource = CocurricularScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
