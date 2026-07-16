<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            // Tracks which admin (admins table/guard) reviewed this
            // verification. Distinct from `reviewed_by`, which FKs to
            // `users` and is reserved for a future user-side reviewer.
            $table->foreignId('reviewed_by_admin_id')
                ->nullable()
                ->after('reviewed_by')
                ->constrained('admins')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_admin_id');
        });
    }
};
