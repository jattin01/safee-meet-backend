<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A paid subscribe no longer writes a local subscription row until the
     * first payment succeeds, so the user's Stripe customer id needs a home
     * of its own — otherwise every retry would mint a new Stripe customer.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['stripe_customer_id']);
                $table->dropColumn('stripe_customer_id');
            });
        }
    }
};
