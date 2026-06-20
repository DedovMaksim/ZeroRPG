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
        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('resource_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('amount')->default(0);

            $table->timestamps();

            $table->unique(['building_id', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventories');
    }
};
