<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_consents')) {
            Schema::create('user_consents', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->enum('consent_type', [
                    'terms', 'privacy', 'location', 'marketing', 'biometric',
                    'kyc', 'data_sharing', 'criminal_background_check',
                ]);
                $table->string('version', 50);
                $table->boolean('accepted');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'consent_type']);
            });
        } elseif (DB::getDriverName() === 'mysql') {
            // This table predates Laravel migrations on production and uses a
            // native enum. Extend it without replacing any existing consent.
            DB::statement("ALTER TABLE user_consents MODIFY consent_type ENUM('terms','privacy','location','marketing','biometric','kyc','data_sharing','criminal_background_check') NOT NULL");
        }

        if (! Schema::hasTable('background_checks')) {
            Schema::create('background_checks', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('provider', 100);
                $table->enum('check_type', ['criminal', 'employment', 'identity', 'watchlist']);
                $table->string('provider_reference_id')->unique();
                $table->enum('status', ['pending', 'clear', 'flagged', 'failed'])->default('pending');
                $table->smallInteger('result_score')->nullable();
                $table->text('result_summary')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('background_checks', function (Blueprint $table): void {
            if (! Schema::hasColumn('background_checks', 'user_verification_id')) {
                $table->unsignedBigInteger('user_verification_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('background_checks', 'subscription_id')) {
                $table->unsignedBigInteger('subscription_id')->nullable()->after('user_verification_id');
            }
            if (! Schema::hasColumn('background_checks', 'plan_id')) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('subscription_id');
            }
            if (! Schema::hasColumn('background_checks', 'consent_id')) {
                $table->char('consent_id', 26)->nullable()->after('plan_id');
            }
            if (! Schema::hasColumn('background_checks', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('provider_reference_id');
            }
            if (! Schema::hasColumn('background_checks', 'provider_status')) {
                $table->string('provider_status', 100)->nullable()->after('status');
            }
            if (! Schema::hasColumn('background_checks', 'result_classification')) {
                $table->string('result_classification', 100)->nullable()->after('provider_status');
            }
            if (! Schema::hasColumn('background_checks', 'request_fingerprint')) {
                $table->string('request_fingerprint', 64)->nullable()->after('result_summary');
            }
            if (! Schema::hasColumn('background_checks', 'provider_response')) {
                $table->longText('provider_response')->nullable()->after('request_fingerprint');
            }
            if (! Schema::hasColumn('background_checks', 'failure_code')) {
                $table->string('failure_code', 100)->nullable()->after('provider_response');
            }
            if (! Schema::hasColumn('background_checks', 'failure_message')) {
                $table->text('failure_message')->nullable()->after('failure_code');
            }
            if (! Schema::hasColumn('background_checks', 'poll_attempts')) {
                $table->unsignedSmallInteger('poll_attempts')->default(0)->after('failure_message');
            }
            if (! Schema::hasColumn('background_checks', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('poll_attempts');
            }
            if (! Schema::hasColumn('background_checks', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('requested_at');
            }
            if (! Schema::hasColumn('background_checks', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('background_checks', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('failed_at');
            }
            if (! Schema::hasColumn('background_checks', 'recheck_of_id')) {
                $table->char('recheck_of_id', 26)->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('background_checks', 'recheck_reason')) {
                $table->text('recheck_reason')->nullable()->after('recheck_of_id');
            }
            if (! Schema::hasColumn('background_checks', 'requested_by_admin_id')) {
                $table->unsignedBigInteger('requested_by_admin_id')->nullable()->after('recheck_reason');
            }
        });

        $indexes = collect(Schema::getIndexes('background_checks'))->pluck('name');
        if (! $indexes->contains('background_checks_idempotency_key_unique')) {
            Schema::table('background_checks', function (Blueprint $table): void {
                $table->unique('idempotency_key');
            });
        }
        if (! $indexes->contains('background_checks_user_id_check_type_status_index')) {
            Schema::table('background_checks', function (Blueprint $table): void {
                $table->index(['user_id', 'check_type', 'status']);
            });
        }
    }

    public function down(): void
    {
        // The base tables predate migrations on production, so rollback only
        // removes integration-owned columns and never destroys legacy data.
        if (Schema::hasTable('background_checks')) {
            Schema::table('background_checks', function (Blueprint $table): void {
                $columns = [
                    'user_verification_id', 'subscription_id', 'plan_id', 'consent_id',
                    'idempotency_key', 'provider_status', 'result_classification',
                    'request_fingerprint', 'provider_response', 'failure_code',
                    'failure_message', 'poll_attempts', 'requested_at', 'submitted_at',
                    'failed_at', 'expires_at', 'recheck_of_id', 'recheck_reason',
                    'requested_by_admin_id',
                ];

                $existing = array_values(array_filter(
                    $columns,
                    fn (string $column): bool => Schema::hasColumn('background_checks', $column),
                ));

                if ($existing !== []) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
