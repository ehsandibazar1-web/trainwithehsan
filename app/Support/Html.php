<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    // به تیترهای h2/h3ِ بدونِ id یک id یکتا (اسلاگ‌شده از متنِ خودشان) اضافه می‌کند — تا هر بخشِ
    // مقاله/صفحه با یک لینکِ #anchor مستقیم قابلِ ارجاع/دیپ‌لینک باشد (که هم برای بازدیدکننده هم
    // برای موتورهای پاسخ‌گوی هوش‌مصنوعی که به یک بخشِ خاص لینک می‌دهند مفید است). h1 عمداً بیرون
    // از این تابع می‌ماند چون h1 خودِ صفحه (تیترِ اصلی) از قبل بیرونِ این بدنه است. تیترهایی که
    // خودشان از قبل id دارند دست‌نخورده می‌مانند؛ تصادفِ اسلاگ با یک -2/-3/... رفع می‌شود
    // (همان قراردادِ پسوندِ برخوردِ نامِ فایل در MediaProcessor).
    public static function withHeadingIds(?string $html): string
    {
        if ($html === null || trim($html) === '' || ! Str::contains($html, ['<h2', '<h3'])) {
            return (string) $html;
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $used = [];
        foreach (iterator_to_array($dom->getElementsByTagName('h2')) as $node) {
            self::assignHeadingId($node, $used);
        }
        foreach (iterator_to_array($dom->getElementsByTagName('h3')) as $node) {
            self::assignHeadingId($node, $used);
        }

        $out = '';
        foreach ($dom->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out !== '' ? $out : $html;
    }

    private static function assignHeadingId(\DOMElement $node, array &$used): void
    {
        if (trim($node->getAttribute('id')) !== '') {
            $used[$node->getAttribute('id')] = true;

            return;
        }

        $slug = Str::slug(trim($node->textContent));
        if ($slug === '') {
            return;
        }

        $id = $slug;
        for ($i = 2; isset($used[$id]); $i++) {
            $id = $slug.'-'.$i;
        }

        $used[$id] = true;
        $node->setAttribute('id', $id);
    }
}
