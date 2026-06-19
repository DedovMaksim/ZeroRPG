<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->integer('level')->default(1);
            $table->integer('xp')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'xp',
            ]);
        });
    }
};