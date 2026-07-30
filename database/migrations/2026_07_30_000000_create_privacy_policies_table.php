<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('privacy_policies')) {
            return;
        }

        Schema::create('privacy_policies', function (Blueprint $table) {
            $table->id();
            $table->longText('content')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('admins')) {
            Schema::table('privacy_policies', function (Blueprint $table) {
                $table->foreign('updated_by')->references('id')->on('admins')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_policies');
    }
};
