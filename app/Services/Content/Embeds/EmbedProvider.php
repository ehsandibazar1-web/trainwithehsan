<?php

namespace App\Services\Content\Embeds;

/**
 * یک ارائه‌دهنده‌ی embed (یوتیوب، ویمئو، فایلِ خودمیزبان، …). ماژولار و مستقل: افزودنِ منبعِ تازه =
 * یک کلاسِ تازه که این را implement کند + یک ورودی در فهرستِ EmbedRenderer::defaultProviders().
 *
 * match() یک href را می‌گیرد و اگر بشناسدش، داده‌ی نرمال‌شده برمی‌گرداند، وگرنه null:
 *   [
 *     'kind'     => 'iframe' | 'video' | 'audio',   // چه چیزی باید در زمانِ کلیک ساخته شود
 *     'provider' => 'youtube' | 'vimeo' | 'file',    // برای کلاسِ CSS/برچسب
 *     'src'      => string,                           // URLِ واقعیِ پخش (nocookie/player/فایل) — فقط
 *                                                     //   در data-attribute می‌نشیند؛ تا کلیکِ کاربر
 *                                                     //   هیچ منبعِ خارجی‌ای بارگذاری نمی‌شود
 *     'label'    => string,                           // متنِ دسترس‌پذیریِ دکمه
 *   ]
 */
interface EmbedProvider
{
    /**
     * @return array{kind: string, provider: string, src: string, label: string}|null
     */
    public function match(string $href): ?array;
}
