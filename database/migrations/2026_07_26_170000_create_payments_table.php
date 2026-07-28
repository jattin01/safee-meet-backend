<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userIdType = Schema::getColumnType('users', 'id');

        Schema::create('payments', function (Blueprint $table) use ($userIdType) {
            $table->id();

            if (in_array($userIdType, ['char', 'string'], true)) {
                $table->char('user_id', 26);
            } else {
                $table->unsignedBigInteger('user_id');
            }

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->unsignedInteger('amount'); // smallest currency unit (paise/cents)
            $table->string('currency', 3)->default('usd');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
