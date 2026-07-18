<?php

namespace App\Services\Content;

use App\Services\Content\Embeds\EmbedProvider;
use App\Services\Content\Embeds\InstagramEmbedProvider;
use App\Services\Content\Embeds\SelfHostedEmbedProvider;
use App\Services\Content\Embeds\TikTokEmbedProvider;
use App\Services\Content\Embeds\VimeoEmbedProvider;
use App\Services\Content\Embeds\YouTubeEmbedProvider;
use Illuminate\Support\Str;

/**
 * پس‌پردازشِ بدنه‌ی محتوا (بعد از Str::sanitizeHtml) برای تبدیلِ «لینکِ تنهای یک پاراگراف» به یک
 * facadeِ click-to-load. طراحیِ عمدی و امن‌تر از بازکردنِ allowlistِ sanitizer:
 *
 *   - در بدنه فقط یک <a href> ذخیره می‌شود (از TipTap و sanitizer دست‌نخورده عبور می‌کند، بی JS build).
 *   - این‌جا، *بعد از* sanitize، آن لینک با یک placeholderِ مورداعتماد (که خودمان از روی شناسه‌ی
 *     validate‌شده می‌سازیم) جایگزین می‌شود — پس هیچ <iframe>/<video> ای هرگز ذخیره یا allow نمی‌شود.
 *   - facade تا کلیکِ کاربر هیچ منبعِ ثالثی (حتی تامبنیل) بارگذاری نمی‌کند؛ ساختِ پخش‌کننده‌ی واقعی
 *     کارِ JSِ سمتِ کلاینت هنگامِ کلیک است (نگاه کنید به master.blade.php).
 *
 * فقط پاراگرافی که تنها محتوایش یک لینک است embed می‌شود (قراردادِ آشنای auto-embed)؛ لینکِ درون‌خطی
 * دست‌نخورده می‌ماند. بدنه‌ای که هیچ نشانه‌ای از منبعِ شناخته‌شده ندارد بدونِ هیچ پردازشی و byte-identical
 * برمی‌گردد (سازگاریِ کامل با محتوای موجود).
 */
class EmbedRenderer
{
    /** @var EmbedProvider[] */
    private array $providers;

    // نشانه‌های سریع — اگر هیچ‌کدام در بدنه نباشد، اصلاً وارد regex نمی‌شویم و ورودی دست‌نخورده برمی‌گردد
    private const HINTS = ['youtube.com', 'youtu.be', 'vimeo.com', 'instagram.com', 'tiktok.com', '.mp4', '.webm', '.mov', '.ogv', '.mp3', '.wav', '.ogg', '.m4a'];

    // پاراگرافی که تنها محتوایش یک <a> است (با هر ویژگیِ <p>، و هر متن/فرمتِ درونِ خودِ لینک). یک‌جا
    // تعریف می‌شود تا render() و extractVideos() از یک الگوی واحد استفاده کنند — نه دو نسخه‌ی موازی.
    // گروهِ ۱ = ویژگی‌های <a>، گروهِ ۲ = متنِ درونِ لینک (برای نامِ VideoObject).
    private const P_STANDALONE_LINK = '~<p[^>]*>\s*<a\b([^>]*)>((?:(?!</a>).)*)</a>\s*</p>~is';

    /**
     * @param  EmbedProvider[]|null  $providers
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? self::defaultProviders();
    }

    /**
     * @return EmbedProvider[]
     */
    public static function defaultProviders(): array
    {
        return [
            new YouTubeEmbedProvider,
            new VimeoEmbedProvider,
            new InstagramEmbedProvider,
            new TikTokEmbedProvider,
            new SelfHostedEmbedProvider,
        ];
    }

    public function render(string $html): string
    {
        if (trim($html) === '' || ! Str::contains($html, self::HINTS)) {
            return $html;
        }

        // پاراگرافی که تنها محتوایش یک <a> است (با هر ویژگیِ <p>، و هر متن/فرمتِ درونِ خودِ لینک)
        return preg_replace_callback(
            self::P_STANDALONE_LINK,
            function (array $m): string {
                if (! preg_match('~\bhref="([^"]+)"~i', $m[1], $h)) {
                    return $m[0];
                }

                // html_entity_decode (نه htmlspecialchars_decode): sanitizer کاراکترهایی مثل «=» را
                // به entityِ عددی (&#61;) کد می‌کند که فقط این تابع بازش می‌کند — وگرنه regexِ providerها
                // نمی‌شناسدشان
                $href = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5);
                $match = $this->detect($href);

                return $match ? $this->facadeHtml($match) : $m[0];
            },
            $html,
        );
    }

    /**
     * @return array{kind: string, provider: string, src: string, label: string}|null
     */
    public function detect(string $href): ?array
    {
        foreach ($this->providers as $provider) {
            if ($match = $provider->match($href)) {
                return $match;
            }
        }

        return null;
    }

    /**
     * فهرستِ همان embedهایی که render() در بدنه به facade تبدیل می‌کند — بدونِ تغییرِ بدنه، فقط برای
     * تشخیص (مثلاً ساختِ VideoObject در VideoSchemaService). چون از همان الگو (P_STANDALONE_LINK) و
     * همان detect() استفاده می‌کند، schema و رندرِ واقعی هرگز از هم جدا نمی‌افتند — یک منبعِ واحدِ حقیقت.
     *
     * @return array<int, array{href: string, text: string, match: array{kind: string, provider: string, src: string, label: string}}>
     */
    public function extractVideos(string $html): array
    {
        if (trim($html) === '' || ! Str::contains($html, self::HINTS)) {
            return [];
        }

        $out = [];

        if (preg_match_all(self::P_STANDALONE_LINK, $html, $all, PREG_SET_ORDER)) {
            foreach ($all as $m) {
                if (! preg_match('~\bhref="([^"]+)"~i', $m[1], $h)) {
                    continue;
                }

                // html_entity_decode مثلِ render() — sanitizer «=» را به &#61; کد می‌کند
                $href = html_entity_decode($h[1], ENT_QUOTES | ENT_HTML5);
                $match = $this->detect($href);

                if (! $match) {
                    continue;
                }

                $out[] = [
                    'href' => $href,
                    'text' => trim(strip_tags($m[2] ?? '')),
                    'match' => $match,
                ];
            }
        }

        return $out;
    }

    /**
     * markupِ facade — مورداعتماد است چون از داده‌ی نرمال‌شده‌ی provider (نه ورودیِ خام) ساخته می‌شود.
     * تا کلیک هیچ منبعِ خارجی‌ای ندارد؛ نسبتِ ۱۶:۹ رزرو شده تا با ساختِ پخش‌کننده CLS رخ ندهد.
     *
     * @param  array{kind: string, provider: string, src: string, label: string}  $match
     */
    public function facadeHtml(array $match): string
    {
        $kind = htmlspecialchars($match['kind'], ENT_QUOTES);
        $provider = htmlspecialchars($match['provider'], ENT_QUOTES);
        $src = htmlspecialchars($match['src'], ENT_QUOTES);
        $label = htmlspecialchars($match['label'], ENT_QUOTES);
        // شناسه‌ی اختیاری (فعلاً فقط تیک‌تاک) → data-video-id در blockquoteِ سمتِ کلاینت
        $id = isset($match['id']) ? ' data-embed-id="'.htmlspecialchars((string) $match['id'], ENT_QUOTES).'"' : '';

        return '<div class="twe-embed twe-embed--'.$provider.' twe-embed--'.$kind.'"'
            .' data-embed-kind="'.$kind.'" data-embed-src="'.$src.'"'.$id
            .' role="button" tabindex="0" aria-label="'.$label.'">'
            .'<span class="twe-embed__play" aria-hidden="true"></span>'
            .'<span class="twe-embed__label">'.$label.'</span>'
            .'</div>';
    }
}
