<?php

namespace App\Filament\Resources\ExtracurricularAttendances\Pages;

use App\Filament\Resources\ExtracurricularAttendances\ExtracurricularAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExtracurricularAttendances extends ManageRecords
{
    protected static string $resource = ExtracurricularAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
