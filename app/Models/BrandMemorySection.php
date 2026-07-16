<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک تکه از حافظه‌ی برند (مثلاً «لحن نوشتار») — بخش‌های پیش‌فرض (is_system=true) با migration
 * seed می‌شوند و قابل‌حذف نیستند، فقط قابل‌غیرفعال‌سازی؛ ادمین می‌تواند بخش سفارشی هم اضافه کند.
 * مقدار واقعی متن روی BrandMemoryValue است (هر بخش، یک ردیف مقدار به‌ازای هر زبان).
 */
class BrandMemorySection extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'description', 'is_enabled', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(BrandMemoryValue::class);
    }

    public function valueFor(string $locale): ?BrandMemoryValue
    {
        return $this->values->firstWhere('locale', $locale);
    }
}
