<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Not a unique index: PJOK/PRAMUKA on Kamis are allowed to double up
            // (see Schedule::isConflictExempt()), so conflicts are checked in the
            // application, not enforced here.
            $table->index(['semester_id', 'day_of_week', 'time_slot_id', 'classroom_id'], 'schedules_classroom_slot_index');
            $table->index(['semester_id', 'day_of_week', 'time_slot_id', 'teacher_id'], 'schedules_teacher_slot_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
