<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedInteger('base_duration_minutes')->default(5);
        });

        // Preserve existing durations for already seeded and custom locations.
        DB::table('locations')->update([
            'base_duration_minutes' => DB::raw('3 + (difficulty * 2)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('base_duration_minutes');
        });
    }
};
