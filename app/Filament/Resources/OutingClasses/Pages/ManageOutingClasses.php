<?php

namespace App\Filament\Resources\OutingClasses\Pages;

use App\Filament\Resources\OutingClasses\OutingClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOutingClasses extends ManageRecords
{
    protected static string $resource = OutingClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
