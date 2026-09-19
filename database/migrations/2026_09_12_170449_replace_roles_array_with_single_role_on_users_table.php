<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every account has only ever held at most one role in practice, so the `roles` JSON
     * array is replaced with a single nullable `role` column — a plain value is both a
     * clearer schema and lets the admin form use a single-select instead of a checkbox grid.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->nullable()->after('position');
        });

        foreach (DB::table('users')->select('id', 'roles')->get() as $user) {
            $roles = json_decode($user->roles ?? '[]', true) ?: [];

            DB::table('users')->where('id', $user->id)->update(['role' => $roles[0] ?? null]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('roles')->nullable()->after('position');
        });

        foreach (DB::table('users')->select('id', 'role')->get() as $user) {
            $roles = filled($user->role) ? [$user->role] : [];

            DB::table('users')->where('id', $user->id)->update(['roles' => json_encode($roles)]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
