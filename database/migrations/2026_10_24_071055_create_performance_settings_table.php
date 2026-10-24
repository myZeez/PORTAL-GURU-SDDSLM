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
        Schema::create('performance_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_scoring_enabled')->default(false);
            $table->json('weights')->nullable();
            $table->unsignedTinyInteger('target_percentage')->default(80);
            $table->json('thresholds')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_settings');
    }
};
