<?php

namespace App\Filament\Actions;

use App\Models\RecordAudit;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * A header button that opens the change history of one kind of record: every correction
 * and removal, newest first, with who did it, what changed and why.
 */
class AuditHistoryAction
{
    /**
     * @param  class-string  $modelClass
     */
    public static function make(string $modelClass, string $heading = 'Riwayat perubahan'): Action
    {
        return Action::make('auditHistory')
            ->label('Riwayat Perubahan')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading($heading)
            ->modalContent(fn () => view('filament.partials.audit-history', [
                'audits' => RecordAudit::query()
                    ->where('subject_type', $modelClass)
                    ->with('actor')
                    ->latest()
                    ->latest('id')
                    ->limit(100)
                    ->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }
}
