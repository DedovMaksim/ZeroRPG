<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('robot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->boolean('equipped')->default(false);
            $table->timestamps();
            $table->index(['robot_id', 'equipped']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robot_items');
    }
};
