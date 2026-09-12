<?php

namespace App\Filament\Resources\Extracurriculars\Pages;

use App\Filament\Resources\Extracurriculars\ExtracurricularResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExtracurriculars extends ManageRecords
{
    protected static string $resource = ExtracurricularResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
