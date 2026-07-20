<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the legacy plan enums now that plan_id (FK to subscription_plans)
     * is the single source of truth. Run only after the previous migration's
     * backfill is confirmed. `users.subscription_status` (trial/active/...)
     * is intentionally kept — that tracks state, not which plan.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'plan')) {
                $table->dropColumn('plan');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'subscription_plan')) {
                $table->dropColumn('subscription_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'plan')) {
                $table->enum('plan', ['free_trial', 'basic', 'premium', 'professional'])->nullable()->after('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'subscription_plan')) {
                $table->enum('subscription_plan', ['free_trial', 'basic', 'premium', 'professional'])->nullable();
            }
        });
    }
};
