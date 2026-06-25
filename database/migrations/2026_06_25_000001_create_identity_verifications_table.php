<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('user_id', 26)->index();
            $table->string('provider')->nullable();
            $table->string('provider_reference_id')->nullable()->index();
            $table->string('verification_level')->default('basic');
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->string('reviewed_by_user_id', 26)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('created_by_user_id', 26)->nullable();
            $table->string('updated_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
