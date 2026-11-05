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
        Schema::create('record_audits', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            // Kept after the record itself is deleted, which is what the trail is for.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action');
            $table->string('label');
            $table->foreignId('actor_id')->constrained('users');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_audits');
    }
};
