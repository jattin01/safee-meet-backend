<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the free-standing plan enums with a real foreign key to the
     * subscription_plans catalog:
     *   - subscriptions.plan_id  → which plan that subscription record is for
     *   - users.plan_id          → the user's current active plan (read shortcut)
     *
     * Both are nullable + nullOnDelete so archiving/removing a plan never
     * deletes a user or their subscription history — the link just goes null.
     * Column creation and constraint creation are checked separately so this
     * stays re-runnable even if a previous attempt added the column but failed
     * before the constraint. The old enum columns are dropped in the next
     * migration.
     */
    public function up(): void
    {
        $this->addPlanColumn('subscriptions', 'user_id');
        $this->addPlanColumn('users', 'subscription_plan');

        $this->addPlanForeignKey('subscriptions');
        $this->addPlanForeignKey('users');

        // Existing data already stores the plan's id in the legacy column, so
        // copy it straight across — only where it points at a plan that still
        // exists (anything else is left NULL = no plan / free).
        $validPlanIds = DB::table('subscription_plans')->pluck('id')->all();

        $this->backfill('users', 'subscription_plan', $validPlanIds);
        $this->backfill('subscriptions', 'plan', $validPlanIds);
    }

    public function down(): void
    {
        foreach (['subscriptions', 'users'] as $table) {
            if ($this->hasPlanForeignKey($table)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$table.'_plan_id_foreign']));
            }
            if (Schema::hasColumn($table, 'plan_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('plan_id'));
            }
        }
    }

    private function addPlanColumn(string $table, string $after): void
    {
        if (!Schema::hasColumn($table, 'plan_id')) {
            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->unsignedBigInteger('plan_id')->nullable()->after($after);
            });
        }
    }

    private function addPlanForeignKey(string $table): void
    {
        if (!$this->hasPlanForeignKey($table)) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreign('plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
            });
        }
    }

    private function hasPlanForeignKey(string $table): bool
    {
        return DB::selectOne('
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME = ?
            LIMIT 1
        ', [$table, 'plan_id', 'subscription_plans']) !== null;
    }

    private function backfill(string $table, string $legacyColumn, array $validPlanIds): void
    {
        if (!Schema::hasColumn($table, $legacyColumn) || !Schema::hasColumn($table, 'plan_id')) {
            return;
        }

        foreach (DB::table($table)->whereNotNull($legacyColumn)->whereNull('plan_id')->get(['id', $legacyColumn]) as $row) {
            $planId = (int) $row->{$legacyColumn};

            if (in_array($planId, $validPlanIds, false)) {
                DB::table($table)->where('id', $row->id)->update(['plan_id' => $planId]);
            }
        }
    }
};
