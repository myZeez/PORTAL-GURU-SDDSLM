<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * School days, numbered like ISO-8601 weekdays (Monday = 1).
 */
enum SchoolDay: int implements HasLabel
{
    case Senin = 1;
    case Selasa = 2;
    case Rabu = 3;
    case Kamis = 4;
    case Jumat = 5;

    public function getLabel(): string
    {
        return $this->name;
    }
}
