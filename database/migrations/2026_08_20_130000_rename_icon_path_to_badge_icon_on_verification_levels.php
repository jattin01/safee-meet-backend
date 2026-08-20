<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('verification_levels', 'icon_path') && !Schema::hasColumn('verification_levels', 'badge_icon')) {
            Schema::table('verification_levels', function (Blueprint $table) {
                $table->renameColumn('icon_path', 'badge_icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('verification_levels', 'badge_icon') && !Schema::hasColumn('verification_levels', 'icon_path')) {
            Schema::table('verification_levels', function (Blueprint $table) {
                $table->renameColumn('badge_icon', 'icon_path');
            });
        }
    }
};
