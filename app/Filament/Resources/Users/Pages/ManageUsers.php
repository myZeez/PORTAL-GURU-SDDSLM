<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Actions\AuditHistoryAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\RecordAudit;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AuditHistoryAction::make(User::class, 'Riwayat perubahan akun'),
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'must_change_password' => true])
                ->after(function (User $record): void {
                    RecordAudit::record($record, RecordAudit::ACTION_UPDATED, $record->auditLabel(), array_fill_keys(array_keys($record->auditSnapshot()), '—'), $record->auditSnapshot(), 'Akun baru dibuat', auth()->user());
                }),
        ];
    }
}
