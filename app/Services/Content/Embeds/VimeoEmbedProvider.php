<?php

namespace App\Services\Content\Embeds;

/**
 * ویمئو — همان تشخیصِ شناسه‌ی home.blade.php. player.vimeo.com فقط هنگامِ کلیک بارگذاری می‌شود.
 */
class VimeoEmbedProvider implements EmbedProvider
{
    public function match(string $href): ?array
    {
        if (! preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $href, $m)) {
            return null;
        }

        return [
            'kind' => 'iframe',
            'provider' => 'vimeo',
            'src' => 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1',
            'label' => 'Play video',
        ];
    }
}
