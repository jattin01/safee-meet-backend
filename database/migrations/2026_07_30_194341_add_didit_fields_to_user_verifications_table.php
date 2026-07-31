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
            $table->string('provider')->nullable()->after('national_id_country');
            $table->string('didit_session_id')->nullable()->unique()->after('provider');
            $table->string('didit_decision_status')->nullable()->after('didit_session_id');
            $table->json('didit_payload')->nullable()->after('didit_decision_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropColumn(['provider', 'didit_session_id', 'didit_decision_status', 'didit_payload']);
        });
    }
};
