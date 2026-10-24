<?php

namespace App\Filament\Pages;

use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\PerformancePeriod;
use App\Filament\Performance\PerformanceCalculator;
use App\Models\Classroom;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\PerformanceSnapshot;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * Teacher performance for an evaluation period: seven indicators worked out only from data
 * the system holds, compared across teachers, with the records behind every number one click
 * away. Waka Kurikulum (and Super Admin) see everyone and can set the scoring weights, save a
 * period to the history, and record findings and follow-ups; the principal sees everyone
 * read-only; every teacher sees only their own. The combined score is a monitoring aid and
 * stays off until the school has agreed the weights.
 */
class PerformanceDashboard extends Page
{
    private const MAX_COMPARED = 4;

    protected string $view = 'filament.pages.performance-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Performance Guru';

    protected static ?string $title = 'Performance Guru';

    public string $periodType = 'month';

    public string $anchor = '';

    public string $month = '';

    public string $semesterId = '';

    public string $from = '';

    public string $to = '';

    public string $teacherId = '';

    public string $classroomId = '';

    public string $subjectId = '';

    public string $responsibility = '';

    public string $indicatorFilter = '';

    public string $statusFilter = '';

    public string $sort = 'name';

    #[Locked]
    public ?int $detailTeacherId = null;

    /**
     * @var list<int>
     */
    #[Locked]
    public array $compare = [];

    public string $evaluationNote = '';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $calculated = null;

    public function mount(): void
    {
        $today = now();

        $this->anchor = $today->toDateString();
        $this->month = $today->format('Y-m');
        $this->semesterId = (string) (Semester::current()?->id ?? '');
        $this->from = $today->copy()->startOfMonth()->toDateString();
        $this->to = $today->toDateString();

        if (! $this->canReviewAll()) {
            $this->detailTeacherId = auth()->id();
        }
    }

    public function canReviewAll(): bool
    {
        return auth()->user()->canReviewPerformance();
    }

    public function canManage(): bool
    {
        return auth()->user()->canManagePerformance();
    }

    public function getSettings(): PerformanceSetting
    {
        return PerformanceSetting::current();
    }

    public function getPeriodTypeEnum(): PerformancePeriod
    {
        return PerformancePeriod::tryFrom($this->periodType) ?? PerformancePeriod::Month;
    }

    /**
     * The evaluated period as concrete dates.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function getPeriod(): array
    {
        return PerformanceCalculator::periodFor(
            $this->getPeriodTypeEnum(),
            $this->anchor,
            $this->month,
            $this->semesterId,
            $this->from,
            $this->to,
        );
    }

    public function getPeriodLabel(): string
    {
        [$from, $to] = $this->getPeriod();

        return $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y');
    }

    /**
     * @return array<string, string|null>
     */
    public function periodQuery(): array
    {
        return [
            'period' => $this->getPeriodTypeEnum()->value,
            'anchor' => $this->anchor,
            'month' => $this->month,
            'semester' => $this->semesterId,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterQuery(): array
    {
        return array_filter([
            'teacher' => $this->teacherId,
            'classroom' => $this->classroomId,
            'subject' => $this->subjectId,
            'responsibility' => $this->responsibility,
            'indicator' => $this->indicatorFilter,
            'status' => $this->statusFilter,
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * Every visible teacher's results for the period, before any filter.
     *
     * @return array<int, array<string, mixed>>
     */
    private function everyone(): array
    {
        if ($this->calculated !== null) {
            return $this->calculated;
        }

        [$from, $to] = $this->getPeriod();

        return $this->calculated = $this->calculator($from, $to)
            ->calculate(PerformanceCalculator::visibleTeachers(auth()->user()));
    }

    private function calculator(CarbonInterface $from, CarbonInterface $to): PerformanceCalculator
    {
        return new PerformanceCalculator($from, $to, $this->getSettings());
    }

    /**
     * The rows of the main table: filtered, then sorted.
     *
     * @return list<array<string, mixed>>
     */
    public function getRows(): array
    {
        [$from, $to] = $this->getPeriod();

        $rows = $this->canReviewAll()
            ? $this->calculator($from, $to)->filter($this->everyone(), [
                'teacher' => ctype_digit($this->teacherId) ? (int) $this->teacherId : null,
                'classroom' => ctype_digit($this->classroomId) ? (int) $this->classroomId : null,
                'subject' => ctype_digit($this->subjectId) ? (int) $this->subjectId : null,
                'responsibility' => $this->responsibility,
                'indicator' => $this->indicatorFilter,
                'status' => $this->statusFilter,
            ])
            : $this->everyone();

        $indicator = PerformanceIndicator::tryFrom($this->sort);

        return collect($rows)
            ->sortBy(fn (array $row): mixed => match (true) {
                $this->sort === 'score' => -($row['score']['value'] ?? -1),
                $indicator !== null => $row['indicators'][$indicator->value]['value'] ?? INF,
                default => $row['teacher']->name,
            })
            ->values()
            ->all();
    }

    /**
     * Averages across the rows shown, ignoring teachers an indicator does not apply to.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{average: ?float, applicable: int, belowTarget: int}>
     */
    public function summarize(array $rows): array
    {
        $target = $this->getSettings()->target_percentage;

        return collect(PerformanceIndicator::cases())
            ->mapWithKeys(function (PerformanceIndicator $indicator) use ($rows, $target): array {
                $values = collect($rows)->map(fn (array $row): ?float => $row['indicators'][$indicator->value]['value'])->filter(fn (?float $v): bool => $v !== null);

                return [$indicator->value => [
                    'average' => $values->isEmpty() ? null : round($values->avg(), 1),
                    'applicable' => $values->count(),
                    'belowTarget' => $values->filter(fn (float $v): bool => $v < $target)->count(),
                ]];
            })
            ->all();
    }

    /**
     * Teachers picked for side-by-side comparison, in the order they were picked.
     *
     * @return list<array<string, mixed>>
     */
    public function getComparison(): array
    {
        if (! $this->canReviewAll()) {
            return [];
        }

        $everyone = $this->everyone();

        return collect($this->compare)->map(fn (int $id): ?array => $everyone[$id] ?? null)->filter()->values()->all();
    }

    /**
     * The teacher whose records are open, with every item behind each number.
     *
     * @return array<string, mixed>|null
     */
    public function getDetail(): ?array
    {
        if ($this->detailTeacherId === null) {
            return null;
        }

        return $this->everyone()[$this->detailTeacherId] ?? null;
    }

    /**
     * @return Collection<int, PerformanceSnapshot>
     */
    public function getHistory(): Collection
    {
        if ($this->detailTeacherId === null) {
            return collect();
        }

        return PerformanceSnapshot::query()
            ->with('savedBy')
            ->where('teacher_id', $this->detailTeacherId)
            ->orderByDesc('period_end')
            ->limit(12)
            ->get();
    }

    /**
     * @return Collection<int, PerformanceFollowUp>
     */
    public function getFollowUps(): Collection
    {
        if ($this->detailTeacherId === null) {
            return collect();
        }

        return PerformanceFollowUp::query()
            ->with(['responsible', 'createdBy'])
            ->where('teacher_id', $this->detailTeacherId)
            ->orderByRaw("case status when 'done' then 1 else 0 end")
            ->orderBy('target_date')
            ->get();
    }

    public function openDetail(int $teacherId): void
    {
        abort_unless($this->canSee($teacherId), 403);

        $this->detailTeacherId = $teacherId;
        $this->evaluationNote = (string) PerformanceSnapshot::query()
            ->where('teacher_id', $teacherId)
            ->whereDate('period_start', $this->getPeriod()[0]->toDateString())
            ->whereDate('period_end', $this->getPeriod()[1]->toDateString())
            ->value('notes');
    }

    public function closeDetail(): void
    {
        if ($this->canReviewAll()) {
            $this->detailTeacherId = null;
        }
    }

    public function toggleCompare(int $teacherId): void
    {
        abort_unless($this->canReviewAll() && $this->canSee($teacherId), 403);

        if (in_array($teacherId, $this->compare, true)) {
            $this->compare = array_values(array_diff($this->compare, [$teacherId]));

            return;
        }

        if (count($this->compare) < self::MAX_COMPARED) {
            $this->compare[] = $teacherId;
        }
    }

    public function clearCompare(): void
    {
        $this->compare = [];
    }

    public function resetFilters(): void
    {
        $this->teacherId = '';
        $this->classroomId = '';
        $this->subjectId = '';
        $this->responsibility = '';
        $this->indicatorFilter = '';
        $this->statusFilter = '';
        $this->sort = 'name';
    }

    private function canSee(int $teacherId): bool
    {
        return $this->canReviewAll() ? isset($this->everyone()[$teacherId]) : $teacherId === auth()->id();
    }

    /**
     * Save one teacher's figures for this period, with the evaluation note typed beside
     * them, so progress can be compared in later periods.
     */
    public function saveTeacherSnapshot(): void
    {
        abort_unless($this->canManage() && $this->detailTeacherId !== null && $this->canSee($this->detailTeacherId), 403);

        $this->validate(['evaluationNote' => ['nullable', 'string', 'max:1000']]);

        $this->storeSnapshot($this->everyone()[$this->detailTeacherId], $this->evaluationNote ?: null);

        Notification::make()->success()->title('Riwayat periode guru ini tersimpan.')->send();
    }

    public function setFollowUpStatus(int $followUpId, string $status): void
    {
        abort_unless($this->canManage(), 403);

        $newStatus = PerformanceFollowUpStatus::tryFrom($status);

        abort_if($newStatus === null, 422);

        PerformanceFollowUp::query()->findOrFail($followUpId)->markAs($newStatus);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeSnapshot(array $result, ?string $notes): void
    {
        [$from, $to] = $this->getPeriod();

        PerformanceSnapshot::query()->updateOrCreate(
            ['teacher_id' => $result['teacher']->id, 'period_start' => $from->toDateString(), 'period_end' => $to->toDateString()],
            [
                'period_type' => $this->getPeriodTypeEnum(),
                'indicators' => collect($result['indicators'])->map(fn (array $indicator): array => [
                    'value' => $indicator['value'],
                    'counted' => $indicator['counted'],
                    'full' => $indicator['full'],
                ])->all(),
                'jp' => $result['jp'],
                'score' => $result['score']['value'] ?? null,
                ...($notes !== null ? ['notes' => $notes] : []),
                'saved_by' => auth()->id(),
            ],
        );
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->summaryPdfAction(),
            $this->saveSnapshotAction(),
            $this->addFollowUpAction(),
            $this->settingsAction(),
        ];
    }

    private function summaryPdfAction(): Action
    {
        return Action::make('summaryPdf')
            ->label('Unduh PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (): string => route('reports.performance.pdf', [...$this->periodQuery(), ...$this->filterQuery(), ...($this->detailTeacherId && ! $this->canReviewAll() ? ['teacher' => $this->detailTeacherId] : [])]))
            ->openUrlInNewTab();
    }

    private function saveSnapshotAction(): Action
    {
        return Action::make('saveSnapshot')
            ->label('Simpan Riwayat Periode')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage())
            ->requiresConfirmation()
            ->modalHeading('Simpan hasil periode ini?')
            ->modalDescription('Angka semua guru pada periode ini disalin ke riwayat supaya perkembangannya bisa dibandingkan. Menyimpan ulang periode yang sama memperbarui angkanya, catatan evaluasi tetap.')
            ->action(function (): void {
                abort_unless($this->canManage(), 403);

                foreach ($this->everyone() as $result) {
                    $this->storeSnapshot($result, null);
                }

                Notification::make()->success()->title('Riwayat periode tersimpan.')->send();
            });
    }

    private function addFollowUpAction(): Action
    {
        return Action::make('addFollowUp')
            ->label('Tambah Temuan / Tindak Lanjut')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => $this->canManage())
            ->modalHeading('Catat temuan dan tindak lanjut')
            ->fillForm(fn (): array => ['teacher_id' => $this->detailTeacherId, 'target_date' => now()->addWeeks(2)->toDateString()])
            ->schema([
                Select::make('teacher_id')
                    ->label('Guru')
                    ->options(fn (): array => PerformanceCalculator::visibleTeachers(auth()->user())->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Select::make('indicator')
                    ->label('Terkait indikator')
                    ->options(PerformanceIndicator::class)
                    ->placeholder('Umum'),
                Textarea::make('finding')->label('Temuan')->required()->rows(2)->maxLength(1000),
                Textarea::make('recommendation')->label('Rekomendasi perbaikan')->required()->rows(2)->maxLength(1000),
                Select::make('responsible_id')
                    ->label('Penanggung jawab')
                    ->helperText('Guru yang dipilih di sini akan melihat tindak lanjut ini, dan penyelesaiannya masuk indikator Tindak lanjut supervisi.')
                    ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                DatePicker::make('target_date')->label('Target penyelesaian')->required()->minDate(now()->startOfDay()),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManage(), 403);

                PerformanceFollowUp::query()->create($data);

                Notification::make()->success()->title('Temuan dan tindak lanjut dicatat.')->send();
            });
    }

    private function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Atur Bobot & Target')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (): bool => $this->canManage())
            ->modalHeading('Bobot skor, target, dan predikat')
            ->modalDescription('Skor gabungan hanya alat bantu monitoring dan pembinaan. Aktifkan setelah sekolah menyepakati bobot; predikat baru tampil setelah semua ambang diisi.')
            ->fillForm(function (): array {
                $settings = $this->getSettings();

                return [
                    'is_scoring_enabled' => $settings->is_scoring_enabled,
                    'weights' => $settings->weightsByIndicator(),
                    'target_percentage' => $settings->target_percentage,
                    'thresholds' => $settings->thresholds ?? [],
                ];
            })
            ->schema([
                Toggle::make('is_scoring_enabled')->label('Tampilkan skor gabungan')->live(),
                ...collect(PerformanceIndicator::cases())->map(fn (PerformanceIndicator $indicator): TextInput => TextInput::make('weights.'.$indicator->value)
                    ->label('Bobot '.$indicator->getLabel().' (%)')
                    ->helperText($indicator->getBasis())
                    ->numeric()->minValue(0)->maxValue(100)->integer()->required())->all(),
                TextInput::make('target_percentage')->label('Target tiap indikator (%)')->numeric()->minValue(1)->maxValue(100)->integer()->required(),
                TextInput::make('thresholds.excellent')->label('Ambang "Sangat Baik" (skor ≥)')->numeric()->minValue(0)->maxValue(100)->integer(),
                TextInput::make('thresholds.good')->label('Ambang "Baik" (skor ≥)')->numeric()->minValue(0)->maxValue(100)->integer(),
                TextInput::make('thresholds.fair')->label('Ambang "Cukup" (skor ≥)')->numeric()->minValue(0)->maxValue(100)->integer(),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManage(), 403);

                $total = array_sum(array_map('intval', $data['weights']));

                if ($data['is_scoring_enabled'] && $total !== 100) {
                    Notification::make()->danger()->title("Total bobot {$total}%, harus 100% untuk mengaktifkan skor.")->send();

                    return;
                }

                $thresholds = array_filter($data['thresholds'] ?? [], fn ($value): bool => $value !== null && $value !== '');

                if ($thresholds !== [] && ! $this->thresholdsDescend($thresholds)) {
                    Notification::make()->danger()->title('Ambang harus menurun: Sangat Baik ≥ Baik ≥ Cukup.')->send();

                    return;
                }

                $this->getSettings()->update([
                    'is_scoring_enabled' => $data['is_scoring_enabled'],
                    'weights' => array_map('intval', $data['weights']),
                    'target_percentage' => (int) $data['target_percentage'],
                    'thresholds' => $thresholds ?: null,
                    'updated_by' => auth()->id(),
                ]);

                Notification::make()->success()->title('Pengaturan performance tersimpan.')->send();
            });
    }

    /**
     * @param  array<string, mixed>  $thresholds
     */
    private function thresholdsDescend(array $thresholds): bool
    {
        $values = collect(['excellent', 'good', 'fair'])->filter(fn (string $key): bool => isset($thresholds[$key]))->map(fn (string $key): int => (int) $thresholds[$key])->values();

        return $values->every(fn (int $value, int $index): bool => $index === 0 || $values[$index - 1] >= $value);
    }

    /**
     * @return Collection<int, Classroom>
     */
    public function getClassrooms(): Collection
    {
        return Classroom::query()->orderBy('grade')->orderBy('code')->get();
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getSubjects(): Collection
    {
        return Subject::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function getTeacherOptions(): Collection
    {
        return PerformanceCalculator::visibleTeachers(auth()->user());
    }

    /**
     * @return Collection<int, Semester>
     */
    public function getSemesters(): Collection
    {
        return Semester::query()->orderByDesc('academic_year')->orderByDesc('term')->get();
    }

    /**
     * Colour class for a percentage against the target.
     */
    public function toneFor(?float $value): string
    {
        if ($value === null) {
            return 'text-gray-400 dark:text-gray-500';
        }

        $target = $this->getSettings()->target_percentage;

        return match (true) {
            $value >= $target => 'text-success-600 dark:text-success-400',
            $value >= $target - 15 => 'text-warning-600 dark:text-warning-400',
            default => 'text-danger-600 dark:text-danger-400',
        };
    }
}
