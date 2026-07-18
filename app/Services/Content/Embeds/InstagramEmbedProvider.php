<?php

namespace App\Services\Content\Embeds;

/**
 * اینستاگرام (پست/ریل/IGTV) — برخلافِ یوتیوب/ویمئو یک <iframe src> ساده ندارد؛ embedِ رسمیِ متا
 * یک <blockquote class="instagram-media"> + //www.instagram.com/embed.js است. پس kind اینجا
 * «instagram» است و JSِ facade هنگامِ کلیک آن blockquote را می‌سازد و embed.js را بار می‌کند —
 * تا کلیکِ کاربر هیچ اسکریپت/منبعی از اینستاگرام بارگذاری نمی‌شود.
 */
class InstagramEmbedProvider implements EmbedProvider
{
    public function match(string $href): ?array
    {
        if (! preg_match('~instagram\.com/(p|reel|reels|tv)/([A-Za-z0-9_-]+)~i', $href, $m)) {
            return null;
        }

        // permalinkِ متعارف — reels (جمع، صفحه‌ی فهرست) به reel (تکی) نگاشت می‌شود
        $type = strtolower($m[1]) === 'reels' ? 'reel' : strtolower($m[1]);

        return [
            'kind' => 'instagram',
            'provider' => 'instagram',
            'src' => 'https://www.instagram.com/'.$type.'/'.$m[2].'/',
            'label' => 'View on Instagram',
        ];
    }
}
