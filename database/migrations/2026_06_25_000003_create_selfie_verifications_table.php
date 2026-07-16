<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('selfie_verifications')) {
            return;
        }

        Schema::create('selfie_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identity_verification_id')->index();
            $table->string('user_id', 26)->index();
            $table->text('selfie_file_url');
            $table->decimal('liveness_score', 5, 2)->nullable();
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('created_by_user_id', 26)->nullable();
            $table->string('updated_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->index(['identity_verification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selfie_verifications');
    }
};
