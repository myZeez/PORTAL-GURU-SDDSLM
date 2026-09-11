<?php

namespace Tests\Feature;

use App\Enums\Term;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_semester_deactivates_the_previous_one(): void
    {
        $previous = Semester::factory()->active()->create();
        $next = Semester::factory()->create();

        $next->update(['is_active' => true]);

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertTrue($next->fresh()->is_active);
        $this->assertTrue(Semester::current()->is($next));
    }

    public function test_the_display_name_combines_the_academic_year_and_term(): void
    {
        $semester = Semester::factory()->create([
            'academic_year' => '2026/2027',
            'term' => Term::Genap,
        ]);

        $this->assertSame('2026/2027 Genap', $semester->name);
    }
}
