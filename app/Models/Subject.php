<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'counts_toward_teaching_load', 'allows_concurrent_scheduling', 'team_name'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Whether the subject is taught by a team (UMMI) rather than by one assigned teacher.
     */
    public function isTeamTaught(): bool
    {
        return filled($this->team_name);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counts_toward_teaching_load' => 'boolean',
            'allows_concurrent_scheduling' => 'boolean',
        ];
    }
}
