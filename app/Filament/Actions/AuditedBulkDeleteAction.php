<?php

namespace App\Filament\Actions;

use App\Models\RecordAudit;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tick rows (or, like in a mailbox, tick the box in the header and then "select all") and
 * remove them in one go. Built for clearing out demo data without deleting row by row, so
 * it asks for confirmation with the exact count and for a reason, and writes one summary
 * line to the change history: how many, which dates, who, why.
 */
class AuditedBulkDeleteAction
{
    /**
     * @param  class-string<Model>  $model
     * @param  string  $noun  what the rows are, in plural, for the dialog ("jurnal")
     * @param  string|null  $warning  extra consequence to spell out in the dialog
     * @param  string|null  $dateColumn  a date column to report the span of, when the model has one
     */
    public static function make(string $model, string $noun, ?string $warning = null, ?string $dateColumn = 'date'): BulkAction
    {
        return BulkAction::make('bulkDelete')
            ->label('Hapus yang dipilih')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()->can('deleteAny', $model))
            ->requiresConfirmation()
            ->modalHeading(fn (HasTable $livewire): string => 'Hapus '.number_format(self::selectedCount($livewire), 0, ',', '.')." {$noun} yang dipilih?")
            ->modalDescription('Data yang dihapus tidak bisa dikembalikan.'.($warning ? " {$warning}" : '').' Penghapusan dicatat di riwayat perubahan beserta alasannya.')
            ->modalSubmitActionLabel('Ya, hapus semua yang dipilih')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan menghapus')
                    ->placeholder('Contoh: membersihkan data demo sebelum dipakai sungguhan')
                    ->required()
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (array $data, HasTable $livewire) use ($model, $noun, $dateColumn): void {
                abort_unless(auth()->user()->can('deleteAny', $model), 403);

                $ids = collect($livewire->getSelectedTableRecords(shouldFetchSelectedRecords: false))->values();

                if ($ids->isEmpty()) {
                    return;
                }

                DB::transaction(function () use ($ids, $model, $noun, $dateColumn, $data): void {
                    $first = null;
                    $last = null;

                    foreach ($ids->chunk(500) as $chunk) {
                        if ($dateColumn) {
                            $first = collect([$first, $model::query()->whereKey($chunk)->min($dateColumn)])->filter()->min();
                            $last = collect([$last, $model::query()->whereKey($chunk)->max($dateColumn)])->filter()->max();
                        }

                        $model::query()->whereKey($chunk)->delete();
                    }

                    $summary = ['Jumlah data' => number_format($ids->count(), 0, ',', '.')];

                    if ($first) {
                        $summary['Tanggal paling awal'] = substr((string) $first, 0, 10);
                        $summary['Tanggal paling akhir'] = substr((string) $last, 0, 10);
                    }

                    RecordAudit::query()->create([
                        'subject_type' => $model,
                        'subject_id' => null,
                        'action' => RecordAudit::ACTION_BULK_DELETED,
                        'label' => 'Hapus massal: '.number_format($ids->count(), 0, ',', '.')." {$noun}",
                        'actor_id' => auth()->id(),
                        'before' => $summary,
                        'after' => null,
                        'reason' => $data['reason'],
                    ]);
                });

                Notification::make()
                    ->success()
                    ->title(number_format($ids->count(), 0, ',', '.')." {$noun} dihapus.")
                    ->send();
            });
    }

    private static function selectedCount(HasTable $livewire): int
    {
        return count($livewire->getSelectedTableRecords(shouldFetchSelectedRecords: false));
    }
}
