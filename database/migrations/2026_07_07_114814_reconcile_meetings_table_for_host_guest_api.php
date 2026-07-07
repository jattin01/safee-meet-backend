<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The live "meetings" table predates the host/guest "Create Meeting" API
     * (creator_user_id + a separate meeting_participants table for
     * multi-party support, different status/meeting_type enum values, no
     * guest_user_id/reference/meeting_date/meeting_time/location/purpose
     * columns at all). Reconcile it additively instead of replacing it —
     * this table holds live, safety/SOS-linked meeting data.
     */
    public function up(): void
    {
        if (Schema::hasColumn('meetings', 'creator_user_id') && !Schema::hasColumn('meetings', 'host_user_id')) {
            DB::statement('ALTER TABLE meetings CHANGE creator_user_id host_user_id CHAR(26) NOT NULL');
        }

        if (Schema::hasColumn('meetings', 'meeting_type') && !Schema::hasColumn('meetings', 'type')) {
            DB::statement("ALTER TABLE meetings CHANGE meeting_type type ENUM('personal','business','employer','other') NOT NULL DEFAULT 'personal'");
        }

        $addedGuestUserId = false;

        Schema::table('meetings', function (Blueprint $table) use (&$addedGuestUserId) {
            if (!Schema::hasColumn('meetings', 'guest_user_id')) {
                $table->char('guest_user_id', 26)->nullable()->after('host_user_id');
                $addedGuestUserId = true;
            }
            if (!Schema::hasColumn('meetings', 'reference')) {
                $table->string('reference')->nullable()->unique();
            }
            if (!Schema::hasColumn('meetings', 'meeting_date')) {
                $table->date('meeting_date')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'meeting_time')) {
                $table->time('meeting_time')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('meetings', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('meetings', 'purpose')) {
                $table->string('purpose')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'item_or_service')) {
                $table->string('item_or_service')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'trust_score_snapshot')) {
                $table->float('trust_score_snapshot')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable();
            }
        });

        if ($addedGuestUserId) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->foreign('guest_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Widen the enums to also accept the values this API's flow uses,
        // without dropping the values existing (SOS-linked) rows may hold.
        if (Schema::hasColumn('meetings', 'type')) {
            DB::statement("ALTER TABLE meetings MODIFY type ENUM('personal','business','employer','other','coffee','marketplace','property','freelance','social','dating') NOT NULL DEFAULT 'personal'");
        }
        DB::statement("ALTER TABLE meetings MODIFY status ENUM('draft','scheduled','active','completed','cancelled','expired','emergency','live','incident_reported') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        // Intentionally no-op: this only widens/adds columns on a live
        // production table and never removes data.
    }
};
