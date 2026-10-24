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
        foreach (['outing_classes', 'pid_reservations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['outing_classes', 'pid_reservations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn('approved_at');
            });
        }
    }
};
