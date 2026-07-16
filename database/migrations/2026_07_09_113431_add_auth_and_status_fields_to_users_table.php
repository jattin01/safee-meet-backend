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
        Schema::table('users', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */
            if (Schema::hasColumn('users', 'firebase_uid')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('firebase_uid');
                });
            }
            if (!Schema::hasColumn('users', 'auth_provider')) {
                $table->enum('auth_provider', [
                    'phone',
                    'google',
                    'apple',
                    'email'
                ])->default('phone')->after('firebase_uid');
            }
            // $table->enum('auth_provider', [
            //     'phone',
            //     'google',
            //     'apple',
            //     'email'
            // ])->default('phone')->after('firebase_uid');

            /*
            |--------------------------------------------------------------------------
            | Account Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'deleted'
            ])->default('active')->after('auth_provider');

            $table->timestamp('account_suspended_at')->nullable()->after('status');
            $table->timestamp('account_deleted_at')->nullable()->after('account_suspended_at');

            $table->text('suspended_reason')->nullable()->after('account_deleted_at');
            $table->text('deleted_reason')->nullable()->after('suspended_reason');

            /*
            |--------------------------------------------------------------------------
            | Onboarding & Verification
            |--------------------------------------------------------------------------
            */
            $table->enum('onboarding_status', [
                'pending',
                'profile_completed',
                'company_completed',
                'completed'
            ])->default('pending')->after('deleted_reason');

            $table->enum('kyc_status', [
                'not_started',
                'pending',
                'approved',
                'rejected'
            ])->default('not_started')->after('onboarding_status');

            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');

            /*
            |--------------------------------------------------------------------------
            | Feature Access
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_chat_enabled')->default(true)->after('kyc_verified_at');
            $table->boolean('is_meeting_enabled')->default(true)->after('is_chat_enabled');
            $table->boolean('is_sos_enabled')->default(true)->after('is_meeting_enabled');

            $table->text('fcm_token')->nullable()->after('is_sos_enabled');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('fcm_token');

            /*
            |--------------------------------------------------------------------------
            | User Activity
            |--------------------------------------------------------------------------
            */
            $table->timestamp('last_login_at')->nullable()->after('fcm_token_updated_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('last_seen_at')->nullable()->after('last_login_ip');
            $table->boolean('is_online')->default(false)->after('last_seen_at');


            $table->string('profile_photo')->nullable()->after('is_online');
            $table->decimal('wallet_balance', 10, 2)->default(0)->after('profile_photo');
            $table->json('device_info')->nullable()->after('wallet_balance');

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('status');
            $table->index('kyc_status');
            $table->index('onboarding_status');
            $table->index('firebase_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropIndex(['status']);
            $table->dropIndex(['kyc_status']);
            $table->dropIndex(['onboarding_status']);
            $table->dropIndex(['firebase_uid']);

            $table->dropColumn([
                'firebase_uid',
                'auth_provider',

                'status',
                'account_suspended_at',
                'account_deleted_at',
                'suspended_reason',
                'deleted_reason',

                'onboarding_status',
                'kyc_status',
                'kyc_verified_at',

                'is_chat_enabled',
                'is_meeting_enabled',
                'is_sos_enabled',

                'fcm_token',
                'fcm_token_updated_at',

                'last_login_at',
                'last_login_ip',
                'last_seen_at',
                'is_online',

                'profile_photo',

                'wallet_balance',

                'device_info',
            ]);
        });
    }
};