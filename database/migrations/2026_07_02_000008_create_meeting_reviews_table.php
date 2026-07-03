<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete(); // who wrote it
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete(); // who it's about
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();

            // Sub-ratings shown on the Reviews & Ratings screen (Punctual / Trustworthy / Responsive)
            $table->boolean('punctual')->nullable();
            $table->boolean('trustworthy')->nullable();
            $table->boolean('responsive')->nullable();

            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reviews');
    }
};
