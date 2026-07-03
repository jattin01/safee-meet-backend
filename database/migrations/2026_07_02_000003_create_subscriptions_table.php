<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['free_trial', 'basic', 'premium', 'professional']);
            $table->decimal('price', 8, 2)->default(0);
            $table->enum('billing_cycle', ['trial', 'monthly'])->default('monthly');
            $table->enum('status', ['trial', 'active', 'expired', 'cancelled'])->default('trial');
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });

        // Editable plan catalog (replaces session-based plan storage)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('monthly_price', 8, 2);
            $table->decimal('yearly_price', 8, 2);
            $table->json('features')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscriptions');
    }
};
