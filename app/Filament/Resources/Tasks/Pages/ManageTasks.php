<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTasks extends ManageRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(Task::class, 'Riwayat penghapusan tugas'),
            CreateAction::make(),
        ];
    }
}
