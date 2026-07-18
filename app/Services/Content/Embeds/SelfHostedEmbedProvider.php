<?php

namespace App\Services\Content\Embeds;

/**
 * فایلِ ویدئو/صوتِ خودمیزبان (رسانه‌ی خودِ سایت) — یک لینک به یک فایلِ mp4/webm/mp3/… روی همین
 * دامنه به یک پخش‌کننده‌ی HTML5 تبدیل می‌شود. عمداً فقط منابعِ هم‌ریشه: لینکِ رسانه‌ی خارجیِ دلخواه
 * embed نمی‌شود (نه امنیت، نه حریمِ خصوصی، نه مالکیتِ آن را داریم). چون خودمیزبان است، «منبعِ
 * ثالث» نیست؛ ولی برای یکدستی و صرفه‌جوییِ پهنای‌باند، پخش‌کننده هم فقط هنگامِ کلیک ساخته می‌شود.
 */
class SelfHostedEmbedProvider implements EmbedProvider
{
    private const VIDEO_EXT = ['mp4', 'webm', 'mov', 'ogv'];

    private const AUDIO_EXT = ['mp3', 'wav', 'ogg', 'm4a'];

    public function match(string $href): ?array
    {
        $host = parse_url($href, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // فقط لینکِ نسبی (بدونِ host) یا همان دامنه‌ی سایت — لینکِ خارجی رد می‌شود
        if ($host !== null && $host !== $appHost) {
            return null;
        }

        $ext = strtolower(pathinfo((string) parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($ext, self::VIDEO_EXT, true)) {
            return ['kind' => 'video', 'provider' => 'file', 'src' => $href, 'label' => 'Play video'];
        }

        if (in_array($ext, self::AUDIO_EXT, true)) {
            return ['kind' => 'audio', 'provider' => 'file', 'src' => $href, 'label' => 'Play audio'];
        }

        return null;
    }
}
