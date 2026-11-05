<?php

namespace App\Filament\Resources\Schedules\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(Schedule::class, 'Riwayat penghapusan jadwal pelajaran'),
            CreateAction::make()
                ->using(fn (array $data): Schedule => ScheduleResource::createSchedules($data))
                ->successNotificationTitle('Jadwal tersimpan.'),
        ];
    }
}
