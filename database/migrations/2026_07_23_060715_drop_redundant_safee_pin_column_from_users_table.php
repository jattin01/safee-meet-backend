<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `safee_pin` was added by 2026_07_02_000001 without checking whether this
     * deployment already had the same field under the name `safee_id`. It did,
     * so both columns ended up existing side by side — all real data lives in
     * `safee_id` (NOT NULL, unique), while `safee_pin` has stayed entirely
     * empty. User::safeeColumn() picks `safee_pin` whenever it exists at all
     * (regardless of whether it holds data), so every read/write/query that
     * goes through it silently missed the real data. Dropping the empty
     * column lets safeeColumn() fall back to `safee_id` again, fixing the
     * admin listing and the PIN/QR/chat search endpoints in one shot — no
     * application code references the physical column name directly.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'safee_pin') && Schema::hasColumn('users', 'safee_id')) {
                $table->dropUnique('users_safee_pin_unique');
                $table->dropColumn('safee_pin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'safee_pin')) {
                $table->string('safee_pin')->nullable()->unique();
            }
        });
    }
};
