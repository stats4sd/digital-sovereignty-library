<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft flag for curriculum content. The database default is false so every row that exists
 * at migration time stays live; the models default *new* rows to true (see HasDraftFlag).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_modules', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('section');
        });

        Schema::table('curriculum_sessions', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('order_column');
        });

        Schema::table('curriculum_session_items', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_session_items', fn (Blueprint $table) => $table->dropColumn('is_draft'));
        Schema::table('curriculum_sessions', fn (Blueprint $table) => $table->dropColumn('is_draft'));
        Schema::table('curriculum_modules', fn (Blueprint $table) => $table->dropColumn('is_draft'));
    }
};
