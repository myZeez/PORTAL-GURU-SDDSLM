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
        Schema::create('teacher_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_attendance_id')->nullable()->constrained('teacher_attendances')->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->date('date');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after');
            $table->text('reason');
            $table->timestamps();

            $table->index(['teacher_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance_logs');
    }
};
