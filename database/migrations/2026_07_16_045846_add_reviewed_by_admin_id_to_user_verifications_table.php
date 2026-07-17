<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks which admin (admins table/guard) reviewed this verification.
     * Distinct from `reviewed_by`, which FKs to `users` and is reserved for
     * a future user-side reviewer.
     *
     * Written defensively: an earlier deploy attempt added this column as
     * `int unsigned` (mismatched against admins.id, which is bigint
     * unsigned) and failed while adding the foreign key, so on some
     * environments the column already exists with the wrong type and no
     * constraint. Safe to re-run in that state.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('user_verifications', 'reviewed_by_admin_id')) {
            Schema::table('user_verifications', function (Blueprint $table) {
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('reviewed_by');
            });
        } elseif ($this->columnType('user_verifications', 'reviewed_by_admin_id') !== 'bigint') {
            DB::statement('ALTER TABLE `user_verifications` MODIFY `reviewed_by_admin_id` BIGINT UNSIGNED NULL');
        }

        if (!$this->hasForeignKey('user_verifications', 'reviewed_by_admin_id', 'admins')) {
            Schema::table('user_verifications', function (Blueprint $table) {
                $table->foreign('reviewed_by_admin_id')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_admin_id');
        });
    }

    private function columnType(string $table, string $column): ?string
    {
        $result = DB::selectOne('
            SELECT DATA_TYPE AS type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$table, $column]);

        return $result->type ?? null;
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable): bool
    {
        return DB::selectOne('
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME = ?
            LIMIT 1
        ', [$table, $column, $referencedTable]) !== null;
    }
};
