<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK link from a user to their current row in the `verification_levels`
     * catalog — additive only. The existing `verification_level` string
     * column is left untouched (still written/read the same way); this FK
     * is what lets us resolve the catalog's `badge_icon` for display.
     * Nullable + nullOnDelete: a user with no approved level (or whose level
     * row gets removed) simply shows no badge, never breaks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('verification_level_id')
                ->nullable()
                ->after('verification_level')
                ->constrained('verification_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verification_level_id');
        });
    }
};
