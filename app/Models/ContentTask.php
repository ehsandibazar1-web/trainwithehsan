<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک کار مشخص روی یک ContentPlan (مثلاً «نوشتن مقدمه»، «تصویر شاخص»، «بازبینی سئو»،
 * «ترجمه»، «تأیید») — status/due_at/assigned_to/notes، دقیقاً طبق چیزی که خواسته شده.
 */
class ContentTask extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'content_plan_id', 'title', 'status', 'due_at', 'assigned_to', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function contentPlan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
