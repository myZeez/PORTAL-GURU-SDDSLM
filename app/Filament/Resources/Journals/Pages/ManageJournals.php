<?php

namespace App\Filament\Resources\Journals\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Actions\SemesterReportAction;
use App\Filament\Resources\Journals\JournalResource;
use App\Models\Journal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJournals extends ManageRecords
{
    protected static string $resource = JournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(Journal::class, 'Riwayat perubahan jurnal'),
            SemesterReportAction::forDate('reports.journals.pdf', 'Unduh PDF Jurnal'),
            CreateAction::make(),
        ];
    }
}
