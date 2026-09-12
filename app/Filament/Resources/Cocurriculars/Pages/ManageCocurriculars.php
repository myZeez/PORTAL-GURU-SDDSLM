<?php

namespace App\Filament\Resources\Cocurriculars\Pages;

use App\Filament\Resources\Cocurriculars\CocurricularResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCocurriculars extends ManageRecords
{
    protected static string $resource = CocurricularResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
