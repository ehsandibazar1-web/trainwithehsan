<?php

namespace App\Support;

class Html
{
    // به تگ‌های <img>ِ داخل یک قطعه HTML که هنوز صفت loading ندارند،
    // loading="lazy" و decoding="async" اضافه می‌کند. مخصوص تصاویرِ بدنه‌ی مقاله/صفحه که همگی
    // زیرِ hero (پایینِ خط تا) قرار دارند، پس lazy کردنشان ظاهر را تغییر نمی‌دهد و فقط لودِ
    // تصاویرِ خارج از دید را تا نزدیک‌شدن به viewport به تعویق می‌اندازد. فقط صفت اضافه می‌کند،
    // هیچ چیزی حذف/جابه‌جا نمی‌شود؛ خروجی بصری کاملاً یکسان می‌ماند. متنِ ذخیره‌شده‌ی body دست
    // نمی‌خورد (این فقط لحظه‌ی رندر است)، پس SeoAuditService/HtmlContentScanner که body خام را
    // می‌خوانند بی‌اثر می‌مانند.
    public static function lazyLoadImages(?string $html): string
    {
        if ($html === null || $html === '') {
            return (string) $html;
        }

        return preg_replace(
            '/<img\b(?![^>]*\bloading=)/i',
            '<img loading="lazy" decoding="async"',
            $html
        );
    }
}
