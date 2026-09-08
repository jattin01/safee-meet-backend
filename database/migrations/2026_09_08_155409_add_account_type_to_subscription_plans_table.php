<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds account-type targeting to the plan catalog. Default 'both' keeps
     * every existing plan visible to normal and employer users alike, so
     * this migration changes no current behaviour on its own — a plan only
     * becomes account-type-restricted once explicitly tagged.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_plans', 'account_type')) {
                $table->enum('account_type', ['normal', 'employer', 'both'])
                    ->default('both')
                    ->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'account_type')) {
                $table->dropColumn('account_type');
            }
        });
    }
};
