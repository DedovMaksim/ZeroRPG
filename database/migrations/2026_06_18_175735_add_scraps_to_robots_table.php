<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->unsignedInteger('scraps')->default(0)->after('integrity');
        });
    }

    public function down(): void
    {
        Schema::table('robots', function (Blueprint $table) {
            $table->dropColumn('scraps');
        });
    }
};