<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AdminAuditLogger
{
    /**
     * Records one admin action. $target is the model acted on, or a plain
     * type string for actions with no single row (e.g. an export).
     *
     * Logging must never break the admin action itself, so failures are
     * reported to the application log instead of thrown.
     */
    public function log(string $action, Model|string $target, ?array $old = null, ?array $new = null): void
    {
        try {
            $request = request();

            AdminAuditLog::create([
                'admin_id' => auth('admin')->id(),
                'action' => $action,
                'target_type' => $target instanceof Model ? class_basename($target) : $target,
                'target_id' => $target instanceof Model ? (string) $target->getKey() : null,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin audit log write failed', [
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Only the attributes that actually changed, as [old, new] pairs, so a
     * log row shows exactly what an edit did.
     */
    public function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($this->normalize($previous) !== $this->normalize($value)) {
                $old[$key] = $previous;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    private function normalize(mixed $value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
