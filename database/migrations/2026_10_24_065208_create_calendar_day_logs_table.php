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
        Schema::create('calendar_day_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('before_status');
            $table->string('after_status');
            $table->text('notes');
            $table->timestamps();

            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_day_logs');
    }
};
