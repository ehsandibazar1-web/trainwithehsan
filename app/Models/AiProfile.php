<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProfile extends Model
{
    protected $fillable = [
        'name', 'provider', 'default_language', 'default_status',
        'default_category', 'default_author', 'notes',
    ];

    /**
     * پیش‌فرض‌های این پروفایل به شکل فیلدهای قالب استاندارد ایمپورت —
     * فقط جاهای خالیِ محتوای واردشده را پر می‌کنند (سرویس ایمپورت اعمال می‌کند).
     */
    public function importDefaults(): array
    {
        return array_filter([
            'language' => $this->default_language,
            'publish_status' => $this->default_status,
            'category' => $this->default_category,
            'author' => $this->default_author,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
