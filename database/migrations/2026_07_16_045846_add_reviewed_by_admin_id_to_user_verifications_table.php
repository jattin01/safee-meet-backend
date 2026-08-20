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
     * A foreign key requires the referencing and referenced columns to be the
     * EXACT same integer type (size + signedness). `admins.id` is not the same
     * type on every deployment (e.g. bigint unsigned locally, int unsigned on
     * a server built from an older schema), which is why hardcoding
     * BIGINT UNSIGNED here failed on the server with error 3780.
     *
     * So we detect admins.id's real column type on the current database and
     * make reviewed_by_admin_id match it exactly — correct everywhere, and
     * self-healing if an earlier attempt left the column as the wrong type.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            if (! Schema::hasColumn('user_verifications', 'reviewed_by_admin_id')) {
                Schema::table('user_verifications', function (Blueprint $table): void {
                    $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                });
            }

            return;
        }

        $adminIdType = $this->columnType('admins', 'id') ?? 'int unsigned';

        if (! Schema::hasColumn('user_verifications', 'reviewed_by_admin_id')) {
            DB::statement("ALTER TABLE `user_verifications` ADD COLUMN `reviewed_by_admin_id` {$adminIdType} NULL AFTER `reviewed_by`");
        } else {
            // Recover from an earlier attempt that created it with a mismatched
            // type: re-align it to whatever admins.id actually is.
            DB::statement("ALTER TABLE `user_verifications` MODIFY `reviewed_by_admin_id` {$adminIdType} NULL");
        }

        if (! $this->hasForeignKey('user_verifications', 'reviewed_by_admin_id', 'admins')) {
            DB::statement('ALTER TABLE `user_verifications`
                ADD CONSTRAINT `user_verifications_reviewed_by_admin_id_foreign`
                FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if ($this->hasForeignKey('user_verifications', 'reviewed_by_admin_id', 'admins')) {
            Schema::table('user_verifications', function (Blueprint $table) {
                $table->dropForeign('user_verifications_reviewed_by_admin_id_foreign');
            });
        }

        if (Schema::hasColumn('user_verifications', 'reviewed_by_admin_id')) {
            Schema::table('user_verifications', function (Blueprint $table) {
                $table->dropColumn('reviewed_by_admin_id');
            });
        }
    }

    /** Full MySQL column type, e.g. "bigint unsigned" / "int unsigned". */
    private function columnType(string $table, string $column): ?string
    {
        $result = DB::selectOne('
            SELECT COLUMN_TYPE AS type
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
