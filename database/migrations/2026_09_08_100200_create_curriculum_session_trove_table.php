<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_session_trove', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trove_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_column')->nullable();
            $table->unique(['curriculum_session_id', 'trove_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_session_trove');
    }
};
