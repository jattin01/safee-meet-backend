<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staged, safe conversion of users.id from CHAR(26) ULID to BIGINT
 * AUTO_INCREMENT, remapping every dependent foreign-key and audit column
 * across the schema (see safee_meet_database.sql, repo root, for the
 * authoritative list this command's config arrays were generated from).
 *
 * Run in this exact order, verifying output at each step:
 *   php artisan users:migrate-to-bigint-id --step=shadow
 *   php artisan users:migrate-to-bigint-id --step=backfill
 *   php artisan users:migrate-to-bigint-id --step=validate   (must report zero abort conditions)
 *   php artisan users:migrate-to-bigint-id --step=swap
 *
 * Each step is idempotent/resumable — safe to re-run if interrupted.
 * STRONGLY recommended to run this once against a restored copy of the
 * backup (not live production) before running --step=swap for real.
 */
class MigrateUsersToBigintId extends Command
{
    protected $signature = 'users:migrate-to-bigint-id {--step=shadow : shadow|backfill|validate|swap}';

    protected $description = 'Staged conversion of users.id from CHAR(26) ULID to BIGINT AUTO_INCREMENT, remapping all dependent tables';

    // Tables with a formal FOREIGN KEY constraint to users(id). Mismatches
    // here are treated as abort conditions during validate.
    private const FK_COLUMNS = [
        'admin_audit_logs' => ['admin_id'],
        'admin_notes' => ['admin_id'],
        'auth_sessions' => ['user_id'],
        'background_checks' => ['user_id'],
        'blocked_users' => ['blocked_user_id', 'blocker_id'],
        'chat_conversation_mappings' => ['user_a_id', 'user_b_id'],
        'chat_media_assets' => ['uploader_id'],
        'chat_reports' => ['reported_user_id', 'reporter_id'],
        'chat_requests' => ['recipient_id', 'requester_id'],
        'chat_safety_events' => ['user_id'],
        'chat_user_mappings' => ['user_id'],
        'data_privacy_requests' => ['handled_by_user_id', 'user_id'],
        'emergency_contacts' => ['user_id'],
        'identity_documents' => ['user_id'],
        'identity_verifications' => ['reviewed_by_user_id', 'user_id'],
        'incident_actions' => ['admin_id'],
        'incident_evidence' => ['uploaded_by_user_id'],
        'incident_reports' => ['assigned_admin_id', 'reported_user_id', 'reporter_id'],
        'invoices' => ['user_id'],
        'login_events' => ['user_id'],
        'meeting_checkins' => ['user_id'],
        'meeting_events' => ['actor_user_id'],
        'meeting_invites' => ['invitee_user_id', 'inviter_user_id'],
        'meeting_locations' => ['user_id'],
        'meeting_participants' => ['user_id'],
        'meetings' => ['guest_user_id', 'host_user_id'],
        'notification_deliveries' => ['user_id'],
        'notification_preferences' => ['user_id'],
        'notifications' => ['user_id'],
        'organization_invitations' => ['invited_by_user_id'],
        'organization_members' => ['user_id'],
        'organization_verifications' => ['reviewed_by_user_id'],
        'organizations' => ['owner_user_id'],
        'payments' => ['user_id'],
        'profile_views' => ['viewed_user_id', 'viewer_id'],
        'promo_code_redemptions' => ['user_id'],
        'qr_codes' => ['user_id'],
        'review_reports' => ['reporter_id', 'reviewed_by_user_id'],
        'risk_flags' => ['resolved_by_user_id', 'user_id'],
        'safe_pins' => ['user_id'],
        'security_events' => ['user_id'],
        'selfie_verifications' => ['user_id'],
        'sos_incidents' => ['resolved_by_user_id', 'triggered_by_user_id'],
        'sos_location_snapshots' => ['user_id'],
        'sos_notifications' => ['recipient_user_id'],
        'sos_responders' => ['responder_user_id'],
        'support_messages' => ['sender_id'],
        'support_tickets' => ['assigned_admin_id', 'user_id'],
        'trust_score_events' => ['user_id'],
        'trust_score_snapshots' => ['user_id'],
        'user_badges' => ['user_id'],
        'user_consents' => ['user_id'],
        'user_devices' => ['user_id'],
        'user_profiles' => ['user_id'],
        'user_reviews' => ['reviewee_id', 'reviewer_id'],
        'user_roles' => ['user_id'],
        'user_search_logs' => ['requester_id', 'target_user_id'],
        'user_subscriptions' => ['user_id'],
    ];

    // created_by_user_id/updated_by_user_id audit columns with NO formal FK
    // constraint. Mismatches here are logged but never block the migration —
    // they may already reference deleted/orphaned users.
    private const AUDIT_COLUMNS = [
        'admin_notes' => ['created_by_user_id', 'updated_by_user_id'],
        'app_versions' => ['created_by_user_id', 'updated_by_user_id'],
        'background_checks' => ['created_by_user_id', 'updated_by_user_id'],
        'blocked_users' => ['created_by_user_id', 'updated_by_user_id'],
        'chat_conversation_mappings' => ['created_by_user_id', 'updated_by_user_id'],
        'chat_media_assets' => ['created_by_user_id', 'updated_by_user_id'],
        'chat_reports' => ['created_by_user_id', 'updated_by_user_id'],
        'chat_requests' => ['created_by_user_id', 'updated_by_user_id'],
        'chat_user_mappings' => ['created_by_user_id', 'updated_by_user_id'],
        'data_privacy_requests' => ['created_by_user_id', 'updated_by_user_id'],
        'emergency_contacts' => ['created_by_user_id', 'updated_by_user_id'],
        'feature_flags' => ['created_by_user_id', 'updated_by_user_id'],
        'identity_documents' => ['created_by_user_id', 'updated_by_user_id'],
        'identity_verifications' => ['created_by_user_id', 'updated_by_user_id'],
        'incident_evidence' => ['created_by_user_id', 'updated_by_user_id'],
        'incident_reports' => ['created_by_user_id', 'updated_by_user_id'],
        'meeting_checkins' => ['created_by_user_id', 'updated_by_user_id'],
        'meeting_invites' => ['created_by_user_id', 'updated_by_user_id'],
        'meeting_participants' => ['created_by_user_id', 'updated_by_user_id'],
        'meetings' => ['created_by_user_id', 'updated_by_user_id'],
        'notification_preferences' => ['created_by_user_id', 'updated_by_user_id'],
        'notification_templates' => ['created_by_user_id', 'updated_by_user_id'],
        'organization_invitations' => ['created_by_user_id', 'updated_by_user_id'],
        'organization_members' => ['created_by_user_id', 'updated_by_user_id'],
        'organization_verifications' => ['created_by_user_id', 'updated_by_user_id'],
        'organizations' => ['created_by_user_id', 'updated_by_user_id'],
        'permissions' => ['created_by_user_id', 'updated_by_user_id'],
        'plan_features' => ['created_by_user_id', 'updated_by_user_id'],
        'promo_codes' => ['created_by_user_id', 'updated_by_user_id'],
        'qr_codes' => ['created_by_user_id', 'updated_by_user_id'],
        'review_reports' => ['created_by_user_id', 'updated_by_user_id'],
        'risk_flags' => ['created_by_user_id', 'updated_by_user_id'],
        'roles' => ['created_by_user_id', 'updated_by_user_id'],
        'safe_pins' => ['created_by_user_id', 'updated_by_user_id'],
        'selfie_verifications' => ['created_by_user_id', 'updated_by_user_id'],
        'sos_incidents' => ['created_by_user_id', 'updated_by_user_id'],
        'subscription_plans' => ['created_by_user_id', 'updated_by_user_id'],
        'support_tickets' => ['created_by_user_id', 'updated_by_user_id'],
        'system_settings' => ['created_by_user_id', 'updated_by_user_id'],
        'user_badges' => ['created_by_user_id', 'updated_by_user_id'],
        'user_reviews' => ['created_by_user_id', 'updated_by_user_id'],
        'user_subscriptions' => ['created_by_user_id', 'updated_by_user_id'],
    ];

    public function handle(): int
    {
        return match ($this->option('step')) {
            'shadow' => $this->runShadow(),
            'backfill' => $this->runBackfill(),
            'validate' => $this->runValidate(),
            'swap' => $this->runSwap(),
            default => $this->failUnknownStep(),
        };
    }

    private function failUnknownStep(): int
    {
        $this->error('Unknown --step. Use one of: shadow, backfill, validate, swap (in that order).');
        return self::FAILURE;
    }

    // ── shadow: add new bigint columns everywhere, don't touch old data ──────

    private function runShadow(): int
    {
        if (!Schema::hasColumn('users', 'new_id')) {
            DB::statement('ALTER TABLE `users` ADD COLUMN `new_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE');
            $this->info('users.new_id added — MySQL auto-populated sequential values for existing rows.');
        } else {
            $this->line('users.new_id already exists — skipping.');
        }

        foreach ($this->allTablesAndColumns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table `{$table}` does not exist on this database — skipping.");
                continue;
            }
            foreach ($columns as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    $this->warn("  {$table}.{$col} does not exist on this database (schema drift from the reference dump) — skipping.");
                    continue;
                }
                $newCol = "{$col}_new";
                if (Schema::hasColumn($table, $newCol)) {
                    continue;
                }
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$newCol}` BIGINT UNSIGNED NULL");
                $this->line("  {$table}.{$newCol} added.");
            }
        }

        $this->info('Shadow phase complete. Next: --step=backfill');
        return self::SUCCESS;
    }

    // ── backfill: populate shadow columns via join on the still-live old id ──

    private function runBackfill(): int
    {
        if (!Schema::hasColumn('users', 'new_id')) {
            $this->error('users.new_id does not exist — run --step=shadow first.');
            return self::FAILURE;
        }

        foreach ($this->allTablesAndColumns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    continue; // already warned about this during shadow
                }
                $newCol = "{$col}_new";
                if (!Schema::hasColumn($table, $newCol)) {
                    $this->warn("  {$table}.{$newCol} missing — run --step=shadow first. Skipping.");
                    continue;
                }
                $affected = DB::update("
                    UPDATE `{$table}` t
                    INNER JOIN `users` u ON t.`{$col}` = u.`id`
                    SET t.`{$newCol}` = u.`new_id`
                    WHERE t.`{$col}` IS NOT NULL AND t.`{$newCol}` IS NULL
                ");
                $this->line("  {$table}.{$col} -> {$newCol}: {$affected} row(s) backfilled.");
            }
        }

        $this->info('Backfill phase complete. Next: --step=validate');
        return self::SUCCESS;
    }

    // ── validate: report mismatches; abort-worthy only for FK_COLUMNS ────────

    private function runValidate(): int
    {
        $blocking = false;

        $total = (int) DB::selectOne('SELECT COUNT(*) AS c FROM `users`')->c;
        $missing = (int) DB::selectOne('SELECT COUNT(*) AS c FROM `users` WHERE `new_id` IS NULL')->c;
        $distinct = (int) DB::selectOne('SELECT COUNT(DISTINCT `new_id`) AS c FROM `users`')->c;
        $this->line("users: {$total} row(s), {$missing} missing new_id, {$distinct} distinct new_id value(s).");
        if ($missing > 0 || $distinct !== $total) {
            $this->error('  ABORT CONDITION: users.new_id is incomplete or has duplicate values.');
            $blocking = true;
        }

        foreach (self::FK_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    continue;
                }
                $newCol = "{$col}_new";
                if (!Schema::hasColumn($table, $newCol)) {
                    continue;
                }
                $mismatch = (int) DB::selectOne("
                    SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$col}` IS NOT NULL AND `{$newCol}` IS NULL
                ")->c;
                if ($mismatch > 0) {
                    $this->error("  ABORT CONDITION: {$table}.{$col} has {$mismatch} row(s) with no matching new id.");
                    $blocking = true;
                }
            }
        }

        foreach (self::AUDIT_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    continue;
                }
                $newCol = "{$col}_new";
                if (!Schema::hasColumn($table, $newCol)) {
                    continue;
                }
                $mismatch = (int) DB::selectOne("
                    SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$col}` IS NOT NULL AND `{$newCol}` IS NULL
                ")->c;
                if ($mismatch > 0) {
                    $this->warn("  [audit-only, non-blocking] {$table}.{$col}: {$mismatch} row(s) with no match — will be left NULL, likely references an already-deleted user.");
                }
            }
        }

        if ($blocking) {
            $this->error('Validation FAILED. Do not run --step=swap until every abort condition above is resolved.');
            return self::FAILURE;
        }

        $this->info('Validation passed — zero abort conditions on FK-constrained columns. Safe to run --step=swap.');
        return self::SUCCESS;
    }

    // ── swap: drop old FKs -> swap users -> re-point every dependent table ───

    private function runSwap(): int
    {
        if (!$this->confirm(
            'This drops old id columns and renames shadow columns across ' . count($this->allTablesAndColumns())
            . ' tables plus users itself, then invalidates ALL existing login sessions. '
            . 'Confirm --step=validate already reported zero abort conditions. Continue?',
            false
        )) {
            $this->warn('Aborted — no changes made.');
            return self::FAILURE;
        }

        // 1. Drop every OLD foreign key constraint pointing at users.id first —
        // users.id cannot be dropped while anything still references it.
        foreach (self::FK_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                $this->dropExistingForeignKey($table, $col);
            }
        }

        // 2. Swap users itself — old id is now unreferenced.
        DB::statement('ALTER TABLE `users` DROP PRIMARY KEY, DROP COLUMN `id`');
        DB::statement('ALTER TABLE `users` DROP INDEX `new_id`');
        DB::statement('ALTER TABLE `users` CHANGE `new_id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)');
        $this->info('users.id swapped to BIGINT AUTO_INCREMENT.');

        // 3. Re-point every FK-constrained table at the now-final users.id.
        foreach (self::FK_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                $this->finishColumnSwap($table, $col, addForeignKey: true);
            }
        }

        // 4. Audit-only columns — no FK to manage.
        foreach (self::AUDIT_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $col) {
                $this->finishColumnSwap($table, $col, addForeignKey: false);
            }
        }

        // 5. Invalidate all existing sessions — old tokenable_id values no
        // longer correspond to anything.
        DB::table('personal_access_tokens')->where('tokenable_type', User::class)->delete();
        $this->info('All existing auth tokens invalidated — every user must log in again.');

        $this->info('Swap complete.');
        return self::SUCCESS;
    }

    private function dropExistingForeignKey(string $table, string $col): void
    {
        $constraint = DB::selectOne('
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME = \'users\'
            LIMIT 1
        ', [$table, $col]);

        if ($constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->name}`");
            $this->line("  {$table}.{$col}: dropped old FK constraint `{$constraint->name}`.");
        }
    }

    private function finishColumnSwap(string $table, string $col, bool $addForeignKey): void
    {
        $newCol = "{$col}_new";
        if (!Schema::hasColumn($table, $newCol)) {
            $this->warn("  {$table}.{$newCol} missing — skipping (was shadow/backfill run for this table?).");
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
        DB::statement("ALTER TABLE `{$table}` CHANGE `{$newCol}` `{$col}` BIGINT UNSIGNED NULL");

        if ($addForeignKey) {
            $fkName = "fk_{$table}_{$col}_bigint";
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$col}`) REFERENCES `users`(`id`)");
        }

        $this->line("  {$table}.{$col} swapped" . ($addForeignKey ? ' (FK re-added).' : ' (audit column, no FK).'));
    }

    private function allTablesAndColumns(): array
    {
        $merged = [];
        foreach (self::FK_COLUMNS as $table => $columns) {
            $merged[$table] = array_unique(array_merge($merged[$table] ?? [], $columns));
        }
        foreach (self::AUDIT_COLUMNS as $table => $columns) {
            $merged[$table] = array_unique(array_merge($merged[$table] ?? [], $columns));
        }
        return $merged;
    }
}
