<?php

namespace App\Filament\Resources\TeacherAttendances\Pages;

use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeacherAttendances extends ManageRecords
{
    protected static string $resource = TeacherAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
