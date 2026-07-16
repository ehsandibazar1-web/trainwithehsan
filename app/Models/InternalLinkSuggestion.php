<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * یک پیشنهاد لینک داخلی: «source باید به target لینک بدهد» — با امتیاز اطمینان، متن لنگر
 * پیشنهادی و دلیل. تولید توسط App\Services\InternalLinking\SuggestionEngine (بازتولیدشدنی،
 * queued)، بازبینی/تایید توسط ادمین در Internal Linking Center.
 */
class InternalLinkSuggestion extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'target_type', 'target_id', 'locale',
        'confidence_score', 'recommended_anchor_text', 'reason', 'status', 'origin', 'approved_at',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }
}
