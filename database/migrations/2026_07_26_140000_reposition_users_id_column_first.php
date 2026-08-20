<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Purely cosmetic: after the ULID->bigint id migration, `id` physically
     * sits at the END of the users table (renaming a column never moves its
     * position) instead of the conventional first column — confusing in
     * tools like phpMyAdmin/Workbench that display raw column order.
     * Move it back to first. No type/data/key change — just position.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $position = DB::selectOne("
            SELECT ORDINAL_POSITION AS pos
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'
        ");

        if ($position && (int) $position->pos === 1) {
            return; // already first — nothing to do
        }

        DB::statement('ALTER TABLE `users` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST');
    }

    public function down(): void
    {
        // Position is cosmetic only — no meaningful rollback.
    }
};
