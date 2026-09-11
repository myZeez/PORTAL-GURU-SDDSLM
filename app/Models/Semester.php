<?php

namespace App\Models;

use App\Enums\Term;
use Carbon\Carbon;
use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_year', 'term', 'starts_on', 'ends_on', 'is_active'])]
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use HasFactory;

    /**
     * Keep a single active semester: activating one deactivates the others.
     */
    protected static function booted(): void
    {
        static::saved(function (Semester $semester): void {
            if ($semester->is_active) {
                static::query()
                    ->whereKeyNot($semester->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Get the semester that is currently running.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => Term::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the display name, e.g. "2026/2027 Ganjil".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->academic_year} {$this->term->getLabel()}");
    }

    /**
     * `starts_on` and `ends_on` are handled by hand rather than Eloquent's built-in
     * `date` cast: that cast still serializes through the model's datetime format
     * (`Y-m-d H:i:s`) when saving, which a real `DATE` column (MySQL) quietly truncates
     * back to `Y-m-d` but a `TEXT` column (SQLite, used in tests) stores verbatim.
     * Storing `Y-m-d` explicitly keeps both environments consistent.
     *
     * @return Attribute<Carbon, string>
     */
    protected function startsOn(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    protected function endsOn(): Attribute
    {
        return self::dateOnly();
    }

    /**
     * @return Attribute<Carbon, string>
     */
    private static function dateOnly(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => filled($value) ? Carbon::parse($value) : null,
            set: fn (Carbon|string|null $value): ?string => filled($value) ? Carbon::parse($value)->toDateString() : null,
        );
    }
}
