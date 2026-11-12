<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * admin_audit_logs.admin_id records the admin-panel user (admins table),
     * but some databases had it pointing at users.id, which rejected every
     * insert. Re-point it at admins.id; deleting an admin keeps their log
     * rows (admin_id set to null).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        foreach ($this->foreignKeys() as $key) {
            if ($key->REFERENCED_TABLE_NAME !== 'admins') {
                DB::statement("ALTER TABLE admin_audit_logs DROP FOREIGN KEY `{$key->CONSTRAINT_NAME}`");
            }
        }

        $hasAdminsKey = collect($this->foreignKeys())->contains(fn ($key) => $key->REFERENCED_TABLE_NAME === 'admins');

        if (! $hasAdminsKey) {
            // Drop log rows whose admin no longer exists, or the new key fails.
            DB::table('admin_audit_logs')
                ->whereNotNull('admin_id')
                ->whereNotIn('admin_id', DB::table('admins')->select('id'))
                ->update(['admin_id' => null]);

            Schema::table('admin_audit_logs', function (Blueprint $table) {
                $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Not restored: the old key pointed at the wrong table.
    }

    private function foreignKeys(): array
    {
        return DB::select("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'admin_audit_logs'
              AND COLUMN_NAME = 'admin_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
    }
};
