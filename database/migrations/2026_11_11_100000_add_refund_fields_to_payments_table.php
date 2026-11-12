<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refund tracking (charge.refunded webhook):
     * - amount_refunded: total refunded so far, in minor units (cents).
     * - refunded_at: when the latest refund was recorded.
     * - status 'partially_refunded': part of the payment was returned; the
     *   rest still counts as collected revenue.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'amount_refunded')) {
                $table->unsignedInteger('amount_refunded')->default(0)->after('amount');
            }

            if (! Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('paid_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending', 'succeeded', 'failed', 'partially_refunded', 'refunded') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('payments')->where('status', 'partially_refunded')->update(['status' => 'succeeded']);
            DB::statement("ALTER TABLE payments MODIFY status ENUM('pending', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount_refunded', 'refunded_at']);
        });
    }
};
