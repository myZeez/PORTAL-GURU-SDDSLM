<?php

namespace App\Filament\Resources\Substitutions\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Actions\SemesterReportAction;
use App\Filament\Resources\Substitutions\SubstitutionResource;
use App\Models\Substitution;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubstitutions extends ManageRecords
{
    protected static string $resource = SubstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(Substitution::class, 'Riwayat perubahan penggantian guru'),
            SemesterReportAction::forDate('reports.substitutions.pdf', 'Unduh PDF Penggantian'),
            CreateAction::make(),
        ];
    }
}
