<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app notification list + per-user preferences.
     *
     * Both tables use a plain bigint auto-increment primary key (NOT a
     * char/ULID id). The only column that must mirror users.id is the user_id
     * foreign key — and since users.id is bigint on some deployments and still
     * char(26) on others, we detect its real type at runtime and match it so
     * the FK is valid everywhere.
     */
    public function up(): void
    {
        $userIdType = $this->usersIdType(); // e.g. "bigint unsigned" or "char(26)"

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) use ($userIdType) {
                $table->id();
                $this->addUserIdColumn($table, $userIdType);
                $table->string('type')->index();       // meeting_approved, sos_triggered, ...
                $table->string('title');
                $table->text('body');
                $table->json('data')->nullable();       // payload for deep-linking
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);      // unread lookups
                $table->index(['user_id', 'created_at']);   // listing
            });
            $this->addUserForeignKey('notifications');
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) use ($userIdType) {
                $table->id();
                $this->addUserIdColumn($table, $userIdType);
                // Channels
                $table->boolean('push_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('sms_enabled')->default(true);
                // Categories
                $table->boolean('meeting_alerts')->default(true);
                $table->boolean('sos_alerts')->default(true);
                $table->boolean('chat_notifications')->default(true);
                $table->boolean('marketing_emails')->default(true);
                $table->timestamps();

                $table->unique('user_id'); // one preference row per user
            });
            $this->addUserForeignKey('notification_preferences');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
    }

    private function usersIdType(): string
    {
        $result = DB::selectOne("
            SELECT COLUMN_TYPE AS type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'
        ");

        return $result->type ?? 'bigint unsigned';
    }

    private function addUserIdColumn(Blueprint $table, string $userIdType): void
    {
        // Match users.id exactly so the FK is compatible on every deployment.
        if (str_starts_with($userIdType, 'char')) {
            $table->char('user_id', 26);
        } else {
            $table->unsignedBigInteger('user_id');
        }
    }

    private function addUserForeignKey(string $tableName): void
    {
        DB::statement("ALTER TABLE `{$tableName}`
            ADD CONSTRAINT `{$tableName}_user_id_foreign`
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE");
    }
};
