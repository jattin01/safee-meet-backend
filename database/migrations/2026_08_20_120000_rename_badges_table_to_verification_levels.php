<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The catalog originally shipped as "Badges" is renamed to
     * "Verification Levels" — same columns/data, just a clearer name for
     * what the admin panel manages. The image field itself stays named
     * `icon_path` (form field `icon`, labelled "Badge image" in the UI).
     */
    public function up(): void
    {
        if (Schema::hasTable('badges') && !Schema::hasTable('verification_levels')) {
            Schema::rename('badges', 'verification_levels');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('verification_levels') && !Schema::hasTable('badges')) {
            Schema::rename('verification_levels', 'badges');
        }
    }
};
