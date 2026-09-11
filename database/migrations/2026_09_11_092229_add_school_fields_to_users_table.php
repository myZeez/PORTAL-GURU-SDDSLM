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
        Schema::table('users', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('id');
            $table->string('position')->nullable()->after('name');
            $table->json('roles')->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'position', 'roles', 'is_active']);
        });
    }
};
