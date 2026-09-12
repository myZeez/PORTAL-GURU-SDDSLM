<?php

namespace App\Filament\Resources\PidReservations\Pages;

use App\Filament\Resources\PidReservations\PidReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePidReservations extends ManageRecords
{
    protected static string $resource = PidReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
