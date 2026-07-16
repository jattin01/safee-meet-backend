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
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Uploaded verification images
            $table->string('face_id_image')->nullable();
            $table->string('national_id_front_image')->nullable();
            $table->string('national_id_back_image')->nullable();

            // Optional national ID details
            $table->string('national_id_number')->nullable();
            $table->string('national_id_country')->nullable();

            /*
             * 0 = Not verified
             * 1 = Level 1
             * Future:
             * 2 = Level 2
             * 3 = Level 3
             * 4 = Level 4
             */
            $table->unsignedTinyInteger('verification_level')
                ->default(0);

            $table->enum('status', [
                'not_submitted',
                'pending',
                'approved',
                'rejected',
            ])->default('not_submitted');

            // Admin who reviewed the verification
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('rejection_reason')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            // One verification record per user
            $table->unique('user_id');

            $table->index([
                'status',
                'verification_level',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_verifications');
    }
};
