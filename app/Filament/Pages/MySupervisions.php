<?php

namespace App\Filament\Pages;

use App\Models\Supervision;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * A read-only list of the signed-in teacher's own upcoming and past supervision visits.
 * Teachers never schedule or edit these themselves — only administrators do, via the
 * "Supervisi" resource.
 */
class MySupervisions extends Page
{
    protected string $view = 'filament.pages.my-supervisions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $navigationLabel = 'Supervisi Saya';

    protected static ?string $title = 'Supervisi Saya';

    protected static ?int $navigationSort = 8;

    /**
     * @return Collection<int, Supervision>
     */
    public function getSupervisions(): Collection
    {
        return Supervision::query()
            ->where('teacher_id', auth()->id())
            ->with(['subject', 'supervisor'])
            ->orderByDesc('date')
            ->get();
    }

    public function isUpcoming(Supervision $supervision): bool
    {
        return $supervision->date->isFuture() || $supervision->date->isToday();
    }
}
