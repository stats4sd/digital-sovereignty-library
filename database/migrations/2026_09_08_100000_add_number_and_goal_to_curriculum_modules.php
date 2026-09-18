<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_modules', function (Blueprint $table) {
            $table->unsignedTinyInteger('number')->nullable()->after('key');
            $table->json('goal')->nullable()->after('learning_outcomes');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_modules', function (Blueprint $table) {
            $table->dropColumn(['number', 'goal']);
        });
    }
};
