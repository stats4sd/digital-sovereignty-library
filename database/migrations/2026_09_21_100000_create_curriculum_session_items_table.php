<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('key', 36);
            $table->string('type', 32);
            $table->foreignId('trove_id')->nullable()->constrained()->nullOnDelete();
            $table->json('intro')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_session_id', 'key']);
            $table->index(['curriculum_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_session_items');
    }
};
