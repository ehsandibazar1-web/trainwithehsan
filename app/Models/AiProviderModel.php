<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک مدل شناخته‌شده برای یک ارائه‌دهنده — کاتالوگی که خودِ ادمین نگه می‌دارد (نه یک لیست هاردکد
 * در کد)، تا فیلد «Default Model» و override هر اکشن از یک Select پر شوند. قیمت هر میلیون توکن
 * اختیاری است — اگر خالی بماند، App\Models\AiUsageLog::estimated_cost_usd برای آن مصرف null
 * می‌ماند، نه یک عدد حدسی.
 */
class AiProviderModel extends Model
{
    protected $fillable = [
        'ai_provider_config_id', 'label', 'model', 'input_price_per_million', 'output_price_per_million',
    ];

    protected function casts(): array
    {
        return [
            'input_price_per_million' => 'decimal:4',
            'output_price_per_million' => 'decimal:4',
        ];
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfig::class, 'ai_provider_config_id');
    }
}
