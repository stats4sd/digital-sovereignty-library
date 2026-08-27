<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->json('term');
            $table->json('definition');
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
