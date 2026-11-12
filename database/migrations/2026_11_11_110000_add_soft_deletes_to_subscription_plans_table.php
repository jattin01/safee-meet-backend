<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deleting a plan from the admin panel now soft-deletes it: it disappears
     * from the admin list and the app, but subscription history, payments and
     * reports keep showing its name instead of "Deleted plan".
     */
    public function up(): void
    {
        if (! Schema::hasColumn('subscription_plans', 'deleted_at')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
