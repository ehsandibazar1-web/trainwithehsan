<?php

namespace App\Services\Content\Embeds;

/**
 * تیک‌تاک — مثلِ اینستاگرام از <blockquote class="tiktok-embed"> + https://www.tiktok.com/embed.js
 * استفاده می‌کند (نه iframeِ ساده). لینکِ کاملِ ویدیو (@user/video/{id}) شناسه‌ی عددی می‌دهد که
 * data-video-id می‌شود؛ لینکِ کوتاه (vm.tiktok.com/…) شناسه ندارد ولی embed.js با cite هم کار می‌کند.
 * ساختِ blockquote و بارگذاریِ embed.js فقط هنگامِ کلیک انجام می‌شود.
 */
class TikTokEmbedProvider implements EmbedProvider
{
    public function match(string $href): ?array
    {
        if (preg_match('~tiktok\.com/@[\w.-]+/video/(\d+)~i', $href, $m)) {
            return [
                'kind' => 'tiktok',
                'provider' => 'tiktok',
                'src' => $this->withoutQuery($href),
                'id' => $m[1],
                'label' => 'Play video',
            ];
        }

        // فرمِ کوتاه — بدونِ شناسه‌ی عددی؛ embed.js از روی cite حل می‌کند
        if (preg_match('~(?:vm\.tiktok\.com/|tiktok\.com/t/)[\w.-]+~i', $href)) {
            return [
                'kind' => 'tiktok',
                'provider' => 'tiktok',
                'src' => $this->withoutQuery($href),
                'label' => 'Play video',
            ];
        }

        return null;
    }

    private function withoutQuery(string $href): string
    {
        return strtok($href, '?') ?: $href;
    }
}
