<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A subject taught by a team (UMMI) has no single teacher: its schedule rows carry the
     * team's name instead and never count toward anyone's teaching hours.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('allows_concurrent_scheduling');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
        });

        $ummi = DB::table('subjects')->where('name', 'like', 'Ummi%')->pluck('id');

        DB::table('subjects')->whereIn('id', $ummi)->update(['team_name' => 'Tim UMMI', 'counts_toward_teaching_load' => false]);
        DB::table('schedules')->whereIn('subject_id', $ummi)->update(['teacher_id' => null]);
    }

    /**
     * Reverse the migrations. The schedule rows keep an empty teacher: who taught UMMI
     * individually is not recoverable, so `teacher_id` is left nullable.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });
    }
};
