<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_module_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->unsignedInteger('order_column')->nullable();
            $table->json('title');
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->string('builds_toward', 36)->nullable();
            $table->timestamps();

            $table->unique(['curriculum_module_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_sessions');
    }
};
