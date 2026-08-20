<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SubscriptionController::subscribe() accepts billing_cycle 'yearly'
        // (SubscriptionPlan has yearly_price / yearly_stripe_price_id), but
        // this enum never included it — inserts with 'yearly' were truncated.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY billing_cycle ENUM('trial', 'monthly', 'yearly') NOT NULL DEFAULT 'monthly'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY billing_cycle ENUM('trial', 'monthly') NOT NULL DEFAULT 'monthly'");
        }
    }
};
