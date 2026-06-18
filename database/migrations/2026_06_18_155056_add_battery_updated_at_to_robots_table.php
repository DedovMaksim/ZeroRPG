<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->timestamp('battery_updated_at')
                ->nullable()
                ->after('battery');
        });
    }

    public function down(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->dropColumn('battery_updated_at');
        });
    }
};