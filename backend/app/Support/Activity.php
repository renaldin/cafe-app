<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class Activity
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?int $userId = null): void
    {
        $request = request();
        ActivityLog::create([
            'user_id' => $userId ?? $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 250),
            'created_at' => now(),
        ]);
    }
}
