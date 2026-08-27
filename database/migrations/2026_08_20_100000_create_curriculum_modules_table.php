<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('section')->index();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('description')->nullable();
            $table->json('note')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_modules');
    }
};
