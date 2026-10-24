<?php

namespace App\Filament\Resources\MonitoringSchedules\Pages;

use App\Filament\Actions\SemesterReportAction;
use App\Filament\Resources\MonitoringSchedules\MonitoringScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMonitoringSchedules extends ManageRecords
{
    protected static string $resource = MonitoringScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SemesterReportAction::forMonth('reports.monitoring.pdf', 'Unduh PDF Monitoring'),
            CreateAction::make(),
        ];
    }
}
