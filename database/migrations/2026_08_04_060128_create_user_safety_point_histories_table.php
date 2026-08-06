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
        Schema::create('user_safety_point_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // identity_verified, verified_meeting, sos_misuse, etc.
            $table->string('event_key');
            // +25, +5, -20
            $table->integer('points');
            // meeting, sos, rating, verification, etc.
            $table->string('reference_type')->nullable();

            // Optional remarks
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('event_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_safety_point_histories');
    }
};