<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_documents', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('identity_verification_id', 26)->index();
            $table->string('user_id', 26)->index();
            $table->string('document_type')->default('other');
            $table->string('issuing_country_code', 2)->default('ZZ');
            $table->string('document_number_hash', 64)->nullable()->index();
            $table->text('front_file_path');
            $table->text('back_file_path')->nullable();
            $table->text('extracted_name_encrypted')->nullable();
            $table->text('extracted_dob_encrypted')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->string('created_by_user_id', 26)->nullable();
            $table->string('updated_by_user_id', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['identity_verification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_documents');
    }
};
