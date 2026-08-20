<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A paid subscription starts here while Stripe waits for the app to
        // confirm the PaymentIntent (3DS/off-session). It must NOT count as
        // active/trial — Subscription::scopeActive() would otherwise grant
        // paid entitlements before the customer has actually paid.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('incomplete', 'trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial'");
            DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('incomplete', 'trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial'");
            DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial'");
        }
    }
};
