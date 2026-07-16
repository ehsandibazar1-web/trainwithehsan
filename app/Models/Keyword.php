<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * کلیدواژه‌ی هدفِ سئو برای یک مقاله یا صفحه (چندریختی) — پایه‌ی «Keyword Mapping» در
 * Internal Linking Center: هم برای نمایش به ادمین، هم برای امتیازدهی به پیشنهادهای لینک داخلی.
 * زبان از رکورد والد (Article/Page) به ارث می‌رسد — ستون locale جداگانه‌ای اینجا نیست تا دو منبع
 * حقیقت برای یک چیز واحد نداشته باشیم.
 */
class Keyword extends Model
{
    protected $fillable = ['keyword'];

    public function keywordable(): MorphTo
    {
        return $this->morphTo();
    }
}
