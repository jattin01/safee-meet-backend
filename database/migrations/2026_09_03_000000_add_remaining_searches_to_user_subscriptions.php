<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_subscriptions', 'safee_pin_search_remaining')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->unsignedInteger('safee_pin_search_remaining')
                    ->nullable()
                    ->after('safee_pin_search');
            });
        }

        // Numeric snapshots receive a concrete balance. Usage already linked
        // to that snapshot is deducted; legacy unlinked history is left alone.
        DB::table('user_subscriptions')
            ->select(['id', 'safee_pin_search'])
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $configured = trim((string) $subscription->safee_pin_search);

                    if (is_numeric($configured)) {
                        $used = DB::table('search_history')
                            ->where('user_subscription_id', $subscription->id)
                            ->whereIn('method', ['pin', 'qr'])
                            ->whereNull('deleted_at')
                            ->distinct()
                            ->count('found_user_id');

                        $remaining = max(0, (int) $configured - $used);
                    } elseif (strcasecmp($configured, 'Unlimited') === 0) {
                        $remaining = null;
                    } else {
                        $remaining = 0;
                    }

                    DB::table('user_subscriptions')
                        ->where('id', $subscription->id)
                        ->update(['safee_pin_search_remaining' => $remaining]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_subscriptions', 'safee_pin_search_remaining')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->dropColumn('safee_pin_search_remaining');
            });
        }
    }
};
