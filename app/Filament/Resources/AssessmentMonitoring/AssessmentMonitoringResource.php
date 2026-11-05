<?php

namespace App\Filament\Resources\AssessmentMonitoring;

use App\Filament\Actions\AuditedBulkDeleteAction;
use App\Filament\Actions\AuditedDeleteAction;
use App\Filament\Resources\AssessmentMonitoring\Pages\ManageAssessmentMonitoring;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Models\Assessment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * "Penilaian Sumatif" under Monitoring: what every teacher has entered, so Waka Kurikulum
 * and Admin Kurikulum can see which classes and teachers have carried out the summative
 * assessment. Waka Kurikulum can also correct or remove an entry a teacher got wrong; the
 * entry itself stays in the teachers' own "Penilaian Sumatif" menu, which nobody else enters.
 */
class AssessmentMonitoringResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static ?string $slug = 'monitoring-penilaian-sumatif';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Penilaian Sumatif';

    protected static ?string $modelLabel = 'penilaian';

    protected static ?string $pluralModelLabel = 'penilaian sumatif';

    protected static ?string $breadcrumb = 'Monitoring Penilaian Sumatif';

    public static function canAccess(): bool
    {
        return auth()->user()?->canMonitorAssessments() ?? false;
    }

    /**
     * Entering an assessment stays with the teacher who gave it.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AssessmentResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classroom', 'subject', 'teacher']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Assessment $record): string => $record->classroom->label)
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran'),
                TextColumn::make('chapter')
                    ->label('Bab'),
                IconColumn::make('is_completed')
                    ->label('Selesai')
                    ->boolean(),
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable(),
                TextColumn::make('question_link')
                    ->label('Link Soal')
                    ->url(fn (Assessment $record): ?string => $record->question_link, shouldOpenInNewTab: true)
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('result_link')
                    ->label('Link Hasil')
                    ->url(fn (Assessment $record): ?string => $record->result_link, shouldOpenInNewTab: true)
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->label),
                SelectFilter::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name'),
                SelectFilter::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->searchable(),
                TernaryFilter::make('is_completed')
                    ->label('Selesai'),
                Filter::make('period')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (Assessment $record, array $data): Assessment {
                        $record->updateWithAudit(auth()->user(), $data);

                        return $record;
                    }),
                AuditedDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AuditedBulkDeleteAction::make(Assessment::class, 'penilaian sumatif'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessmentMonitoring::route('/'),
        ];
    }
}
