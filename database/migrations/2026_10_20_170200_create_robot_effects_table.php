<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('robot_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('robot_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('stat');
            $table->integer('modifier');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['robot_id', 'key']);
            $table->index(['robot_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('robot_effects');
    }
};
