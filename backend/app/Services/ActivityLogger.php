<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Record an admin action against an optional subject model.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function log(string $action, ?Model $subject = null, ?array $changes = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip' => Request::ip(),
        ]);
    }
}
