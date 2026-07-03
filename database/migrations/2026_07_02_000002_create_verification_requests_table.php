<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['level1', 'level2', 'professional']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Level 1 — ID upload / OCR / selfie
            $table->string('drivers_license_front')->nullable();
            $table->string('drivers_license_back')->nullable();
            $table->string('selfie')->nullable();
            $table->boolean('face_match_passed')->nullable();
            $table->boolean('liveness_check_passed')->nullable();
            $table->boolean('anti_spoof_passed')->nullable();

            // OCR extracted data snapshot at time of submission
            $table->json('ocr_extracted')->nullable();

            // Level 2 — background screening
            $table->json('background_check_result')->nullable();

            // Professional
            $table->string('business_license')->nullable();
            $table->string('professional_credentials')->nullable();
            $table->string('insurance_document')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
