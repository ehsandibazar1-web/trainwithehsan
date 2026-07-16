<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * محتوای یک بخش حافظه‌ی برند به یک زبان مشخص — تاریخچه‌ی نسخه‌ها با همان مکانیزم فعالیت‌های
 * Article (spatie/laravel-activitylog) ثبت می‌شود، نه یک جدول نسخه‌ی جداگانه؛ بازیابی یک نسخه‌ی
 * قدیمی یعنی نوشتن دوباره‌ی content آن رویداد فعالیت (نگاه کنید به App\Filament\Pages\BrandMemory).
 */
class BrandMemoryValue extends Model
{
    use LogsActivity;

    protected $fillable = ['brand_memory_section_id', 'locale', 'content'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(BrandMemorySection::class, 'brand_memory_section_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['content'])
            ->logOnlyDirty()
            ->useLogName('brand_memory_value');
    }
}
