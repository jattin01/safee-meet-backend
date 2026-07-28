<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'monthly_stripe_price_id')) {
                $table->string('monthly_stripe_price_id')->nullable();
            }
            if (! Schema::hasColumn('subscription_plans', 'yearly_stripe_price_id')) {
                $table->string('yearly_stripe_price_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_stripe_price_id', 'yearly_stripe_price_id']);
        });
    }
};
