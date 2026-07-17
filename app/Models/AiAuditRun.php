<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک اجرای AI Agent (دستی یا هفتگیِ زمان‌بندی‌شده) — نگاه کنید به App\Services\AiAgent\AgentAuditService.
 */
class AiAuditRun extends Model
{
    protected $fillable = [
        'trigger_type', 'status', 'started_at', 'finished_at',
        'found_count', 'new_count', 'resolved_count', 'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function recommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class, 'audit_run_id');
    }
}
