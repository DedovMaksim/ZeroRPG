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
        Schema::table('robots', function (Blueprint $table) {
            $table->unsignedInteger('logistics_level')->default(1)->after('xp');
            $table->unsignedInteger('logistics_xp')->default(0)->after('logistics_level');
        });
    }

    public function down(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->dropColumn([
                'logistics_level',
                'logistics_xp',
            ]);
        });
    }
};
