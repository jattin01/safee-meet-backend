<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['normal', 'employer'])->default('normal')->after('role');
            $table->string('company_name')->nullable()->after('account_type');
            $table->string('employer_code')->nullable()->after('company_name'); // e.g. EMP-123456
            $table->string('job_title')->nullable()->after('employer_code');
        });

        Schema::create('trusted_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('relationship')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_contacts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'company_name', 'employer_code', 'job_title']);
        });
    }
};
