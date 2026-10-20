<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->text('description')->nullable();
            $table->integer('cpu_bonus')->default(0);
            $table->integer('ram_bonus')->default(0);
            $table->integer('ssd_bonus')->default(0);
            $table->integer('loot_chance_bonus')->default(0);
            $table->integer('rare_loot_chance_bonus')->default(0);
            $table->integer('event_chance_bonus')->default(0);
            $table->integer('ambush_chance_reduction')->default(0);
            $table->integer('battery_cost_reduction')->default(0);
            $table->integer('expedition_speed_bonus')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
