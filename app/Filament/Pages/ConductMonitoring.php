<?php

namespace App\Filament\Pages;

use App\Enums\ClassMonitoringGroup;
use App\Enums\ClassMonitoringItemStatus;
use App\Filament\Resources\MonitoringSchedules\MonitoringScheduleResource;
use App\Models\MonitoringItem;
use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The 28-item checklist screen for one scheduled classroom monitoring visit. Reached
 * from {@see MonitoringScheduleResource}'s
 * "Mulai Monitoring" row action rather than its own menu entry.
 *
 * @property-read Schema $form
 */
class ConductMonitoring extends Page
{
    protected string $view = 'filament.pages.conduct-monitoring';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static bool $shouldRegisterNavigation = false;

    public MonitoringSchedule $schedule;

    public MonitoringResult $result;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/monitoring-schedules/{record}/conduct';
    }

    public function mount(int $record): void
    {
        $this->schedule = MonitoringSchedule::with('classroom')->findOrFail($record);

        abort_unless(auth()->user()->can('create', MonitoringResult::class), 403);

        $this->result = $this->schedule->result()->with('items')->first()
            ?? MonitoringResult::startFor($this->schedule);

        $this->form->fill([
            'notes' => $this->result->notes,
            'recommendation' => $this->result->recommendation,
            'due_date' => $this->result->due_date,
            'items' => $this->result->items->mapWithKeys(
                fn (MonitoringItem $item): array => [$item->id => $item->status?->value]
            )->all(),
        ]);
    }

    public function getTitle(): string
    {
        return "Monitoring {$this->schedule->classroom->label}";
    }

    public function form(Schema $schema): Schema
    {
        $sections = $this->result->items
            ->groupBy(fn (MonitoringItem $item): string => $item->group->value)
            ->map(fn ($items, string $groupValue): Section => Section::make(ClassMonitoringGroup::from($groupValue)->getLabel())
                ->schema(
                    $items->map(fn (MonitoringItem $item) => Radio::make("items.{$item->id}")
                        ->label($item->label)
                        ->options(collect(ClassMonitoringItemStatus::cases())
                            ->mapWithKeys(fn (ClassMonitoringItemStatus $status): array => [$status->value => $status->getLabel()])
                            ->all())
                        ->inline()
                        ->required())
                        ->all()
                ))
            ->values()
            ->all();

        return $schema
            ->components([
                Form::make([
                    ...$sections,
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2),
                    Textarea::make('recommendation')
                        ->label('Rekomendasi')
                        ->rows(2),
                    DatePicker::make('due_date')
                        ->label('Tenggat Tindak Lanjut'),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make([
                            Action::make('save')
                                ->label('Simpan Hasil Monitoring')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data['items'] ?? [] as $itemId => $status) {
            MonitoringItem::whereKey($itemId)->update(['status' => $status]);
        }

        $this->result->update([
            'notes' => $data['notes'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $this->result->computeScore();
        $this->result->refresh();

        Notification::make()->success()->title('Hasil monitoring tersimpan.')->send();
    }
}
