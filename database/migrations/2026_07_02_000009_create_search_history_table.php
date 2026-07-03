<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('searcher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('found_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('query'); // the PIN or QR code text that was searched
            $table->enum('method', ['pin', 'qr'])->default('pin');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_history');
    }
};
