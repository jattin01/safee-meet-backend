<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupon_redemptions')) {
            return;
        }

        $userIdType = Schema::getColumnType('users', 'id');

        Schema::create('coupon_redemptions', function (Blueprint $table) use ($userIdType) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();

            if (in_array($userIdType, ['char', 'string'], true)) {
                $table->char('user_id', 26);
            } else {
                $table->unsignedBigInteger('user_id');
            }
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // The subscription this redemption was applied to (nullable —
            // set once the subscribe() flow actually creates the subscription).
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();

            $table->decimal('discount_amount', 8, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
