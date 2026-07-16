<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف تاریخچه‌ی «از مرحله‌ی X به مرحله‌ی Y» — غیرقابل‌تغییر (فقط created_at، بدون
 * updated_at)، پایه‌ی محاسبه‌ی آمار داشبورد (میانگین زمان انتشار/بازبینی). نگاه کنید به
 * App\Models\ContentPlan::moveToStage().
 */
class ContentPlanStageTransition extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['content_plan_id', 'from_stage_id', 'to_stage_id', 'changed_by'];

    public function contentPlan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class);
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
