<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons')) {
            return;
        }

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 8, 2);

            // Null plan_id = coupon is valid for any plan; a set plan_id
            // restricts it to that one plan only.
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();

            // Null billing_cycle = valid for both monthly and yearly.
            $table->enum('billing_cycle', ['monthly', 'yearly'])->nullable();

            $table->unsignedInteger('max_redemptions')->nullable(); // null = unlimited
            $table->unsignedInteger('max_redemptions_per_user')->default(1);
            $table->unsignedInteger('times_redeemed')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // References the admins table id, but no FK constraint since the
            // admin guard model may not always be present in every deployment.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
