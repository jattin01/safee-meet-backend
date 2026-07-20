<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Turns subscription_plans from a display-only catalog into the source of
     * truth that actual subscription logic reads from:
     *   - slug: stable code-level identifier (survives admin renames), and the
     *     key subscriptions.plan_id / users.plan_id resolve against.
     *   - trial_days: free intro window (e.g. Free Trial = 100 days), null = none.
     *   - pin_search_limit: SAFEE PIN search/chat quota; null = unlimited.
     *   - is_active: soft-archive flag so retired plans stay linkable to history.
     *
     * The canonical 5-plan catalog (prices, Basic Limited, etc.) is enforced by
     * SubscriptionPlanSeeder — this migration only adds structure and gives every
     * existing row a slug so the plan_id backfill in the next migration can run.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_plans', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('subscription_plans', 'trial_days')) {
                $table->unsignedSmallInteger('trial_days')->nullable()->after('yearly_price');
            }
            if (!Schema::hasColumn('subscription_plans', 'pin_search_limit')) {
                $table->unsignedInteger('pin_search_limit')->nullable()->after('trial_days');
            }
            if (!Schema::hasColumn('subscription_plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        // Backfill a slug for every existing row. Known catalog names get the
        // canonical underscore slug (matches the legacy enum values so the FK
        // backfill lines up); anything else falls back to a slugified name.
        $nameToSlug = [
            'Free Trial' => 'free_trial',
            'Basic Limited' => 'basic_limited',
            'Basic' => 'basic',
            'Premium' => 'premium',
            'Professional' => 'professional',
        ];

        foreach (DB::table('subscription_plans')->whereNull('slug')->orWhere('slug', '')->get() as $plan) {
            $slug = $nameToSlug[$plan->name] ?? Str::slug((string) $plan->name, '_');
            DB::table('subscription_plans')->where('id', $plan->id)->update(['slug' => $slug]);
        }

        // Dedupe: if two rows ended up with the same slug (e.g. an old duplicate
        // "Premium"), keep the lowest id and drop the rest so the unique index
        // below can be created safely.
        $seen = [];
        foreach (DB::table('subscription_plans')->orderBy('id')->get() as $plan) {
            if (isset($seen[$plan->slug])) {
                DB::table('subscription_plans')->where('id', $plan->id)->delete();
            } else {
                $seen[$plan->slug] = true;
            }
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'trial_days', 'pin_search_limit', 'is_active']);
        });
    }
};
