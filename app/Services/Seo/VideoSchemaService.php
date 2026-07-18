<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\Content\EmbedRenderer;
use Illuminate\Support\Str;

/**
 * می‌سازد: فهرستی از آرایه‌های VideoObject (schema.org) برای ویدیوهایی که همین حالا روی صفحه نمایش
 * داده می‌شوند — صفحه‌ی اصلی (ردیفِ ویدیو + ویدیوهای اعضا)، و ویدیوهای درون‌متنیِ مقاله/صفحه. این
 * «Video SEO» است: فقط داده‌ی ساختاریِ نامرئی (JSON-LD) اضافه می‌شود تا موتورهای جست‌وجو ویدیوها
 * را به‌عنوان ویدیو بشناسند (rich result)؛ هیچ فیلدِ ادمینِ تازه، هیچ تغییری در ظاهر/رندرِ عمومی.
 *
 * تشخیصِ اینکه یک لینک «ویدیو»ست و از کدام ارائه‌دهنده، *یک منبعِ واحدِ حقیقت* دارد:
 * App\Services\Content\EmbedRenderer (همان providerهایی که facadeِ کلیک‌برای‌بارگذاری را می‌سازند).
 * این‌جا هیچ regexِ مستقلی برای «آیا این یوتیوب است؟» نیست — فقط از detect()/extractVideos() آن
 * استفاده می‌کنیم و خروجی‌اش را به فیلدهای schema نگاشت می‌کنیم. پس رندرِ واقعی و structured data
 * هرگز از هم جدا نمی‌افتند، و همین سرویس هم schemaِ HTML و هم Video Sitemap را تغذیه می‌کند.
 *
 * هر ویدیویی که منبع (فایل یا embedِ شناخته‌شده) یا thumbnailUrl نداشته باشد رد می‌شود، تا فقط
 * VideoObjectهای معتبر منتشر شوند (Google برای VideoObject به thumbnailUrl و یک منبع نیاز دارد).
 * فقط سه نوعِ پشتیبانی‌شده: فایلِ خودمیزبان، یوتیوب، ویمئو — اینستاگرام/تیک‌تاک خانه‌ی متعارفشان روی
 * خودِ پلتفرم است و VideoObject روی دامنه‌ی ما برایشان ساخته نمی‌شود.
 */
class VideoSchemaService
{
    private EmbedRenderer $embeds;

    public function __construct(?EmbedRenderer $embeds = null)
    {
        $this->embeds = $embeds ?? new EmbedRenderer;
    }

    /**
     * @param  array<string, mixed>  $s  تنظیماتِ صفحه‌ی اصلی (home.{locale}.*) به‌شکلِ کلید→مقدار
     * @param  array<int, array<string, mixed>>  $members  ردیف‌های ریپیترِ اعضا
     * @return array<int, array<string, mixed>> فهرستِ VideoObjectها (خالی اگر هیچ ویدیویی نباشد)
     */
    public function forHomepage(array $s, array $members): array
    {
        $videos = [];

        // ردیفِ ویدیو (۱..۳) — همان کپشن‌های پیش‌فرضِ home.blade.php تا نام با آنچه کاربر می‌بیند یکی باشد
        $captionDefaults = [
            'Why train martial arts & self-defense',
            'How the training works',
            'What is self-defense & martial sport',
        ];
        foreach ([1, 2, 3] as $i) {
            $name = $this->value($s, "video{$i}_caption") ?: $captionDefaults[$i - 1];
            $video = $this->build(
                $name,
                $name,
                $this->value($s, "video{$i}_embed"),
                $this->value($s, "video{$i}_file"),
                $this->value($s, "video{$i}_thumb"),
            );
            if ($video) {
                $videos[] = $video;
            }
        }

        // ویدیوهای نتایجِ اعضا — عکسِ عضو نقشِ تامبنیل (poster) را دارد، دقیقاً مثلِ رندرِ صفحه
        foreach ($members as $m) {
            $memberName = trim((string) ($m['name'] ?? '')) ?: 'Member';
            $video = $this->build(
                $memberName.' — member result',
                $memberName.' — member result video',
                (string) ($m['video_embed'] ?? ''),
                (string) ($m['video_file'] ?? ''),
                (string) ($m['photo'] ?? ''),
            );
            if ($video) {
                $videos[] = $video;
            }
        }

        return $videos;
    }

    /**
     * VideoObjectهای ویدیوهای درون‌متنیِ یک مقاله — همان لینک‌هایی که در بدنه به پخش‌کننده تبدیل می‌شوند.
     * تامبنیل: یوتیوب از شناسه، وگرنه عکسِ شاخصِ مقاله (fallbackِ نماینده)؛ تاریخِ انتشار = published_at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forArticle(Article $article): array
    {
        return $this->fromBody(
            (string) $article->body,
            $article->title,
            $this->firstNonEmpty([$article->meta_description, $article->excerpt, $this->plainSummary($article->body), $article->title]),
            $this->recordImageUrl($article->image_path),
            optional($article->published_at)->toIso8601String(),
        );
    }

    /**
     * VideoObjectهای ویدیوهای درون‌متنیِ یک صفحه‌ی مستقل — مثلِ مقاله، ولی صفحه excerpt ندارد.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPage(Page $page): array
    {
        return $this->fromBody(
            (string) $page->body,
            $page->title,
            $this->firstNonEmpty([$page->meta_description, $this->plainSummary($page->body), $page->title]),
            $this->recordImageUrl($page->image_path),
            optional($page->published_at ?? $page->updated_at)->toIso8601String(),
        );
    }

    /**
     * موتورِ مشترکِ ویدیوهای درون‌متنی — از EmbedRenderer::extractVideos() (منبعِ واحدِ تشخیص) عبور
     * می‌کند و هر لینکِ یوتیوب/ویمئو/فایلِ خودمیزبانِ معتبر را به یک VideoObject نگاشت می‌کند.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fromBody(string $body, string $fallbackName, string $description, ?string $thumbFallback, ?string $uploadDate): array
    {
        $videos = [];
        $seen = [];

        foreach ($this->embeds->extractVideos($body) as $item) {
            $match = $item['match'];
            $embedUrl = $this->embedUrlFromMatch($match);
            $contentUrl = $this->selfHostedVideoUrl($match);

            // فقط یوتیوب/ویمئو/ویدیوی خودمیزبان → VideoObject؛ اینستاگرام/تیک‌تاک/صوت رد می‌شوند
            if (! $embedUrl && ! $contentUrl) {
                continue;
            }

            $thumbnailUrl = $this->youTubeThumbFromMatch($match) ?: $thumbFallback;

            // VideoObject بدونِ thumbnailUrl معتبر نیست — رد می‌شود تا داده‌ی ناقص منتشر نشود
            if (! $thumbnailUrl) {
                continue;
            }

            // بدونِ داده‌ی ساختاریِ تکراری برای یک منبعِ یکسان روی همان صفحه
            $key = $embedUrl ?: $contentUrl;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // نامِ لینک اگر واقعی باشد (نه خودِ URL)، وگرنه عنوانِ مقاله/صفحه
            $name = ($item['text'] !== '' && ! $this->looksLikeUrl($item['text'])) ? $item['text'] : $fallbackName;

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $name,
                'description' => $description !== '' ? $description : $name,
                'thumbnailUrl' => $thumbnailUrl,
            ];

            if ($uploadDate) {
                $schema['uploadDate'] = $uploadDate;
            }
            if ($contentUrl) {
                $schema['contentUrl'] = $contentUrl;
            }
            if ($embedUrl) {
                $schema['embedUrl'] = $embedUrl;
            }

            $videos[] = $schema;
        }

        return $videos;
    }

    /**
     * یک VideoObject از داده‌ی خامِ صفحه‌ی اصلی — یا null اگر منبع/تامبنیلِ معتبر نداشته باشد.
     *
     * @return array<string, mixed>|null
     */
    private function build(string $name, string $description, string $embedRaw, string $filePath, string $thumbPath): ?array
    {
        $embedUrl = $embedRaw !== '' ? $this->embedUrlFromMatch($this->embeds->detect($embedRaw)) : null;
        $contentUrl = filled($filePath) ? asset('storage/'.ltrim($filePath, '/')) : null;

        // بدونِ منبع (نه فایلِ آپلودشده، نه embedِ شناخته‌شده) → ویدیویی وجود ندارد
        if (! $embedUrl && ! $contentUrl) {
            return null;
        }

        $thumbnailUrl = $this->thumbnailUrl($thumbPath, $embedRaw);

        // VideoObject بدونِ thumbnailUrl معتبر نیست — رد می‌شود تا داده‌ی ناقص منتشر نشود
        if (! $thumbnailUrl) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $name,
            'description' => $description,
            'thumbnailUrl' => $thumbnailUrl,
        ];

        if ($contentUrl) {
            $schema['contentUrl'] = $contentUrl;
            // uploadDate فقط برای فایلِ آپلودشده که ردیفِ Media دارد (created_at) — برای embed حذف
            // می‌شود (Google آن را «توصیه‌شده» می‌داند نه «الزامی»)
            if ($uploadDate = $this->uploadDate($filePath)) {
                $schema['uploadDate'] = $uploadDate;
            }
        }

        if ($embedUrl) {
            $schema['embedUrl'] = $embedUrl;
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function value(array $s, string $key): string
    {
        $val = $s[$key] ?? null;

        return ($val !== null && $val !== '') ? (string) $val : '';
    }

    // از یک matchِ providerِ EmbedRenderer، embedUrlِ متعارفِ schema (فقط یوتیوب/ویمئو). شناسه از
    // خروجیِ نرمال‌شده‌ی خودِ provider خوانده می‌شود، نه از ورودیِ خام — پس نمی‌تواند از تشخیصِ
    // authoritativeِ provider جدا بیفتد؛ فقط شناسه‌ای را که provider قبلاً درآورده بازمی‌خواند.
    private function embedUrlFromMatch(?array $match): ?string
    {
        if (! $match) {
            return null;
        }

        if (($match['provider'] ?? '') === 'youtube' && preg_match('~/embed/([A-Za-z0-9_-]{11})~', (string) ($match['src'] ?? ''), $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (($match['provider'] ?? '') === 'vimeo' && preg_match('~/video/(\d+)~', (string) ($match['src'] ?? ''), $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    // تامبنیلِ ثابتِ یوتیوب از روی شناسه (بدونِ نیاز به API). ویمئو/سایر بدونِ عکسِ آپلودشده تامبنیلِ
    // مشتق ندارند → null، پس صداکننده باید fallback (عکسِ شاخص) بدهد وگرنه آن ویدیو رد می‌شود.
    private function youTubeThumbFromMatch(?array $match): ?string
    {
        if ($match && ($match['provider'] ?? '') === 'youtube'
            && preg_match('~/embed/([A-Za-z0-9_-]{11})~', (string) ($match['src'] ?? ''), $m)) {
            return 'https://img.youtube.com/vi/'.$m[1].'/hqdefault.jpg';
        }

        return null;
    }

    // فقط ویدیوی خودمیزبان (kind=video, provider=file) contentUrl می‌دهد — صوت (audio) ویدیو نیست
    private function selfHostedVideoUrl(array $match): ?string
    {
        if (($match['provider'] ?? '') !== 'file' || ($match['kind'] ?? '') !== 'video') {
            return null;
        }

        return $this->absoluteUrl((string) ($match['src'] ?? ''));
    }

    // تامبنیلِ صفحه‌ی اصلی: عکسِ آپلودشده اگر باشد، وگرنه تامبنیلِ مشتقِ یوتیوب
    private function thumbnailUrl(string $thumbPath, string $embedRaw): ?string
    {
        if (filled($thumbPath)) {
            return asset('storage/'.ltrim($thumbPath, '/'));
        }

        return $embedRaw !== '' ? $this->youTubeThumbFromMatch($this->embeds->detect($embedRaw)) : null;
    }

    private function uploadDate(string $filePath): ?string
    {
        return Media::where('disk_path', $filePath)->first()?->created_at?->toIso8601String();
    }

    // عکسِ شاخصِ رکورد به‌عنوان تامبنیلِ نماینده‌ی ویدیوی خودمیزبان/ویمئو (که تامبنیلِ مشتق ندارند).
    // عمداً فایلِ اصلی (نه WebP) تا هیچ کوئریِ Media روی هر درخواست زده نشود (Media::forRecord کوئری دارد).
    private function recordImageUrl(?string $imagePath): ?string
    {
        return filled($imagePath) ? asset('storage/'.ltrim($imagePath, '/')) : null;
    }

    private function absoluteUrl(string $src): string
    {
        return Str::startsWith(strtolower($src), ['http://', 'https://']) ? $src : url($src);
    }

    private function looksLikeUrl(string $text): bool
    {
        return Str::startsWith(strtolower(trim($text)), ['http://', 'https://', 'www.']);
    }

    /**
     * @param  array<int, string|null>  $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $c) {
            if (filled($c)) {
                return trim((string) $c);
            }
        }

        return '';
    }

    private function plainSummary(?string $html): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) strip_tags((string) $html)) ?? ''), 200, '');
    }
}
