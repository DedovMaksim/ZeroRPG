<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            $table->unsignedInteger('battery_cost_paid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            $table->dropColumn('battery_cost_paid');
        });
    }
};
