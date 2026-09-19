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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('is_active');
            $table->string('nik', 16)->nullable()->after('photo_path');
            $table->string('nuptk')->nullable()->after('nik');
            $table->string('birthplace')->nullable()->after('nuptk');
            $table->date('birthdate')->nullable()->after('birthplace');
            $table->string('gender')->nullable()->after('birthdate');
            $table->text('address')->nullable()->after('gender');
            $table->string('phone')->nullable()->after('address');
            $table->string('last_education')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'photo_path',
                'nik',
                'nuptk',
                'birthplace',
                'birthdate',
                'gender',
                'address',
                'phone',
                'last_education',
            ]);
        });
    }
};
