<?php

namespace App\Filament\Resources\DailyRoutines\Pages;

use App\Filament\Resources\DailyRoutines\DailyRoutineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyRoutines extends ManageRecords
{
    protected static string $resource = DailyRoutineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
