<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Users pick and activate their own plan, so a new account starts with
     * no plan at all. 'none' = never had (or has no) running plan, kept
     * distinct from 'expired' / 'cancelled' so the app can show the right
     * message. It must NOT grant access — only trial/active do.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('none', 'incomplete', 'trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'none'");
        }

        // Users whose status claims a running plan they don't actually have
        // (created with the old 'trial' default). Fall back to their latest
        // subscription's status, or 'none' if they never subscribed.
        $liveStatuses = ['trial', 'active'];

        DB::table('users')
            ->whereIn('subscription_status', ['trial', 'active', 'incomplete'])
            ->whereNotExists(fn ($query) => $query->from('subscriptions')
                ->whereColumn('subscriptions.user_id', 'users.id')
                ->whereIn('subscriptions.status', $liveStatuses)
                ->whereNull('subscriptions.deleted_at'))
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user) {
                $latestStatus = DB::table('subscriptions')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->value('status');

                DB::table('users')->where('id', $user->id)->update([
                    'subscription_status' => in_array($latestStatus, ['expired', 'cancelled'], true) ? $latestStatus : 'none',
                ]);
            });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('users')->where('subscription_status', 'none')->update(['subscription_status' => 'trial']);
            DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('incomplete', 'trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial'");
        }
    }
};
