<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_history') && ! Schema::hasColumn('search_history', 'user_subscription_id')) {
            Schema::table('search_history', function (Blueprint $table) {
                $table->foreignId('user_subscription_id')
                    ->nullable()
                    ->after('searcher_id')
                    ->constrained('user_subscriptions')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('meetings') && ! Schema::hasColumn('meetings', 'user_subscription_id')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->foreignId('user_subscription_id')
                    ->nullable()
                    ->after('host_user_id')
                    ->constrained('user_subscriptions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meetings') && Schema::hasColumn('meetings', 'user_subscription_id')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_subscription_id');
            });
        }

        if (Schema::hasTable('search_history') && Schema::hasColumn('search_history', 'user_subscription_id')) {
            Schema::table('search_history', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_subscription_id');
            });
        }
    }
};
