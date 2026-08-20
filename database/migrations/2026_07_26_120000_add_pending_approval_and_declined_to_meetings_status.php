<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds the guest-approval flow's two new statuses. Additive only —
     * existing rows keep whatever status they already have.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meetings MODIFY status ENUM('draft','scheduled','active','completed','cancelled','expired','emergency','live','incident_reported','pending_approval','declined') NOT NULL DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        // Intentionally no-op: never destructive on a live table.
    }
};
