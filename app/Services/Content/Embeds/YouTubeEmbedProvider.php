<?php

namespace App\Services\Content\Embeds;

/**
 * یوتیوب — همان تشخیصِ شناسه‌ی home.blade.php (watch?v= / youtu.be / embed / shorts / live).
 * src از دامنه‌ی privacy-friendlyِ youtube-nocookie.com ساخته می‌شود و فقط هنگامِ کلیک بارگذاری
 * می‌شود (نه در زمانِ رندر) — پس هیچ کوکی/درخواستی به یوتیوب پیش از تعاملِ کاربر نمی‌رود.
 */
class YouTubeEmbedProvider implements EmbedProvider
{
    public function match(string $href): ?array
    {
        if (! preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $href, $m)) {
            return null;
        }

        return [
            'kind' => 'iframe',
            'provider' => 'youtube',
            'src' => 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1&rel=0',
            'label' => 'Play video',
        ];
    }
}
