<?php

namespace App\Filament\Actions;

use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;

/**
 * A "Hapus" button that asks for confirmation and a reason before it removes a daily
 * record, and leaves the reason in the record's change history. The model does the removal
 * through `deleteWithAudit()`, so the record and its trail change together or not at all.
 */
class AuditedDeleteAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading('Hapus data ini?')
            ->modalDescription('Data yang dihapus tidak bisa dikembalikan. Penghapusan dicatat di riwayat perubahan beserta alasannya.')
            ->modalSubmitActionLabel('Ya, hapus')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan menghapus')
                    ->required()
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->using(fn (Model $record, array $data): bool => $record->deleteWithAudit(auth()->user(), $data['reason']));
    }
}
