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
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('base_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('key');

            $table->string('name');

            $table->unsignedInteger('level')->default(1);

            $table->string('status')->default('active');

            $table->unsignedInteger('capacity')->default(0);

            $table->timestamps();

            $table->unique(['base_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
