<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->char('subscription_id', 26)
                ->unique();

            $table->decimal('price', 8, 2)
                ->default(0);

            $table->enum('billing_cycle', [
                'trial',
                'monthly',
                'yearly',
            ])->default('monthly');

            $table->enum('status', [
                'incomplete',
                'trial',
                'active',
                'expired',
                'cancelled',
            ])->default('trial');

            $table->unsignedSmallInteger('trial_days')->nullable();

            /*
             * Subscription Features
             */

            // Value / Limit based features
            // $table->unsignedInteger('safee_pin_search')
            //     ->nullable();

            // $table->unsignedInteger('meeting_history')
            //     ->nullable();

            $table->string('safee_pin_search')->nullable();
            $table->string('meeting_history')->nullable();

            // Boolean features
            $table->boolean('level_1_verification')->default(false);
            $table->boolean('level_2_clearance')->default(false);
            $table->boolean('verified_badge_display')->default(false);
            $table->boolean('qr_generation')->default(false);
            $table->boolean('trust_score_calculation')->default(false);
            $table->boolean('safety_score_analytics')->default(false);
            $table->boolean('trusted_contact_alerts')->default(false);
            $table->boolean('premium_badge')->default(false);

            /*
             * Subscription Dates
             */

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('renews_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            /*
             * Stripe
             */

            $table->string('stripe_customer_id')
                ->nullable();

            $table->string('stripe_subscription_id')
                ->index()
                ->nullable();

            /*
             * Relationships
             */

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['user_id', 'status']);

            /*
             * Foreign Keys
             */

            $table->foreign('subscription_id')
                ->references('subscription_id')
                ->on('subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
