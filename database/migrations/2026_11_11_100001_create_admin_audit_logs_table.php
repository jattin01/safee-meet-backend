<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin audit trail: who changed what, when, with before/after values.
     * Some environments already have this table (created outside the
     * migrations), so it is only created when missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_audit_logs')) {
            return;
        }

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('action', 150)->index();
            $table->string('target_type', 100);
            $table->char('target_id', 26)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->dateTime('created_at', 6);

            $table->index(['target_type', 'target_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Not dropped: the table may pre-date this migration and hold history.
    }
};
