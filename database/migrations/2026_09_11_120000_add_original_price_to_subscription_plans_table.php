<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // MRP / "flat" price shown struck-through next to the actual
            // (discounted) monthly_price / yearly_price — e-commerce style.
            // Null = no strikethrough, plan shows at its plain price.
            if (! Schema::hasColumn('subscription_plans', 'monthly_original_price')) {
                $table->decimal('monthly_original_price', 8, 2)->nullable()->after('monthly_price');
            }

            if (! Schema::hasColumn('subscription_plans', 'yearly_original_price')) {
                $table->decimal('yearly_original_price', 8, 2)->nullable()->after('yearly_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'monthly_original_price')) {
                $table->dropColumn('monthly_original_price');
            }

            if (Schema::hasColumn('subscription_plans', 'yearly_original_price')) {
                $table->dropColumn('yearly_original_price');
            }
        });
    }
};
