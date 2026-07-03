<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('otp_code')->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('otp_code');

            $table->enum('role', ['standard', 'professional', 'admin'])
                ->default('standard')->after('phone_verified_at');

            $table->enum('verification_level', ['none', 'level1', 'level2', 'professional'])
                ->default('none')->after('role');

            $table->enum('badge', [
                'none', 'level1_verified', 'level2_verified_background_checked', 'verified_professional',
            ])->default('none')->after('verification_level');

            $table->enum('subscription_plan', ['free_trial', 'basic', 'premium', 'professional'])
                ->nullable()->after('badge');

            $table->enum('subscription_status', ['trial', 'active', 'expired', 'cancelled'])
                ->default('trial')->after('subscription_plan');

            $table->string('safee_pin')->nullable()->unique()->after('subscription_status');

            // OCR-extracted identity fields (Level 1)
            $table->date('dob')->nullable()->after('safee_pin');
            $table->string('address')->nullable()->after('dob');
            $table->string('id_number')->nullable()->after('address');

            $table->float('trust_score')->nullable()->after('id_number'); // e.g. 94
            $table->float('rating')->nullable()->after('trust_score'); // e.g. 4.9, avg of meeting reviews
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'otp_code', 'phone_verified_at', 'role',
                'verification_level', 'badge', 'subscription_plan', 'subscription_status',
                'safee_pin', 'dob', 'address', 'id_number', 'trust_score', 'rating',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
