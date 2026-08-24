<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables involved in account deletion (deleteAccountByPhone) that need
     * to support soft-deletes so a soft-deleted user's related data stays
     * consistent/recoverable instead of being hard-deleted underneath them.
     */
    private array $tables = [
        'notifications',
        'notification_preferences',
        'identity_verifications',
        'user_verifications',
        'emergency_contacts',
        'subscriptions',
        'payments',
        'search_history',
        'meetings',
        'meeting_locations',
        'incidents',
        'verification_requests',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
