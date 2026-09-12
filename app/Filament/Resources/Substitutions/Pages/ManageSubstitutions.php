<?php

namespace App\Filament\Resources\Substitutions\Pages;

use App\Filament\Resources\Substitutions\SubstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubstitutions extends ManageRecords
{
    protected static string $resource = SubstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
