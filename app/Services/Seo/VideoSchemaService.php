<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
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

    // fallbackِ تامبنیلِ سراسری (عکسِ هیروِ صفحه‌ی «درباره» = عکسِ برندِ نماینده که همه‌جای سایت هم
    // نقشِ عکسِ نویسنده را دارد) — تنبل و یک‌بار در هر نمونه‌ی سرویس بارگذاری می‌شود، فقط وقتی ویدیویی
    // تامبنیلِ اختصاصی نداشته باشد؛ تضمین می‌کند هیچ ویدیوی پشتیبانی‌شده‌ای به‌خاطرِ نبودِ تامبنیل حذف نشود.
    private bool $defaultThumbLoaded = false;

    private ?string $defaultThumb = null;

    public function __construct(?EmbedRenderer $embeds = null)
    {
        $this->embeds = $embeds ?? new EmbedRenderer;
    }

    /**
     * @param  array<string, mixed>  $s  تنظیماتِ صفحه‌ی اصلی (home.{locale}.*) به‌شکلِ کلید→مقدار
     * @param  array<int, array<string, mixed>>  $members  ردیف‌های ریپیترِ اعضا
     * @return array<int, array<string, mixed>> فهرستِ VideoObjectها (خالی اگر هیچ ویدیویی نباشد)
     */
    public function forHomepage(array $s, array $members, ?string $fallbackUploadDate = null): array
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
                $fallbackUploadDate,
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
                $fallbackUploadDate,
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
            optional($article->published_at ?? $article->updated_at)->toIso8601String(),
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
     * متادیتای Open Graph / Twitter Player برای ویدیوی *اصلیِ* (اولین) یک صفحه — یا null اگر هیچ
     * ویدیویی نباشد. از همان خروجیِ forArticle()/forPage() (منبعِ واحد) خوانده می‌شود، پس بدونِ
     * محاسبه‌ی دوباره. ویدیوی embed (یوتیوب/ویمئو) علاوه بر og:video یک Twitter *Player* Card هم
     * می‌گیرد؛ فایلِ خودمیزبان فقط og:video (video/mp4) می‌گیرد و Player Card نه — چون X برای
     * twitter:player یک URLِ iframe لازم دارد و یک mp4ِ خام iframe نیست (کارتِ خراب بهتر از هیچ نیست).
     *
     * @param  array<int, array<string, mixed>>  $videoObjects
     * @return array{url: string, secure: bool, type: string, is_embed: bool, title: string, description: string, thumbnail: ?string}|null
     */
    public function primarySocialVideo(array $videoObjects): ?array
    {
        $v = $videoObjects[0] ?? null;
        if (! $v) {
            return null;
        }

        $embed = $v['embedUrl'] ?? null;
        $content = $v['contentUrl'] ?? null;
        $url = $embed ?: $content;

        if (! $url) {
            return null;
        }

        return [
            'url' => $url,
            'secure' => Str::startsWith($url, 'https://'),
            'type' => $embed ? 'text/html' : 'video/mp4',
            'is_embed' => (bool) $embed,
            'title' => (string) ($v['name'] ?? ''),
            'description' => (string) ($v['description'] ?? ''),
            'thumbnail' => $v['thumbnailUrl'] ?? null,
        ];
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

            // تامبنیل: یوتیوب‌مشتق → عکسِ شاخصِ رکورد → fallbackِ سراسری (H3 — تا هیچ ویدیویی حذف نشود)
            $thumbnailUrl = $this->youTubeThumbFromMatch($match) ?: $thumbFallback ?: $this->defaultThumbnail();

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
                'name' => $name !== '' ? $name : 'Video',
                'description' => $description !== '' ? $description : ($name !== '' ? $name : 'Video'),
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

        return $this->attachSelfHostedDurations($videos);
    }

    /**
     * برای ویدیوهای خودمیزبانِ درون‌متنی، مدتِ زمان را با *یک* کوئریِ گروهی (whereIn) اضافه می‌کند —
     * فقط وقتی صفحه واقعاً ویدیوی خودمیزبان دارد، پس روی صفحاتِ بدونِ ویدیو هیچ کوئریِ اضافه‌ای نیست.
     *
     * @param  array<int, array<string, mixed>>  $videos
     * @return array<int, array<string, mixed>>
     */
    private function attachSelfHostedDurations(array $videos): array
    {
        // contentUrl → disk_path (فقط ویدیوهای خودمیزبان contentUrl دارند)
        $paths = [];
        foreach ($videos as $v) {
            if (! empty($v['contentUrl']) && ($dp = $this->diskPathFromContentUrl($v['contentUrl']))) {
                $paths[$v['contentUrl']] = $dp;
            }
        }

        if (! $paths) {
            return $videos;
        }

        $durations = Media::whereIn('disk_path', array_values($paths))
            ->get(['disk_path', 'duration_seconds'])
            ->keyBy('disk_path');

        foreach ($videos as &$v) {
            if (! empty($v['contentUrl']) && isset($paths[$v['contentUrl']])
                && ($iso = $durations->get($paths[$v['contentUrl']])?->duration_iso8601)) {
                $v['duration'] = $iso;
            }
        }
        unset($v);

        return $videos;
    }

    /**
     * یک VideoObject از داده‌ی خامِ صفحه‌ی اصلی — یا null اگر منبع/تامبنیلِ معتبر نداشته باشد.
     *
     * @return array<string, mixed>|null
     */
    private function build(string $name, string $description, string $embedRaw, string $filePath, string $thumbPath, ?string $fallbackUploadDate = null): ?array
    {
        $embedUrl = $embedRaw !== '' ? $this->embedUrlFromMatch($this->embeds->detect($embedRaw)) : null;
        $contentUrl = filled($filePath) ? asset('storage/'.ltrim($filePath, '/')) : null;

        // بدونِ منبع (نه فایلِ آپلودشده، نه embedِ شناخته‌شده) → ویدیویی وجود ندارد
        if (! $embedUrl && ! $contentUrl) {
            return null;
        }

        // تامبنیل: آپلودشده/یوتیوب‌مشتق، وگرنه fallbackِ سراسری (H3 — تا هیچ ویدیویی حذف نشود)
        $thumbnailUrl = $this->thumbnailUrl($thumbPath, $embedRaw) ?: $this->defaultThumbnail();

        // VideoObject بدونِ thumbnailUrl معتبر نیست — رد می‌شود تا داده‌ی ناقص منتشر نشود
        if (! $thumbnailUrl) {
            return null;
        }

        // ردیفِ Media یک‌بار خوانده می‌شود و هم uploadDate (created_at) هم duration را می‌دهد — نه دو کوئری
        $media = ($contentUrl && filled($filePath)) ? $this->mediaForDiskPath($filePath) : null;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            // name و description همیشه غیرخالی — Google هر دو را می‌خواهد (name الزامی)
            'name' => $name !== '' ? $name : ($description !== '' ? $description : 'Video'),
            'description' => $description !== '' ? $description : ($name !== '' ? $name : 'Video'),
            'thumbnailUrl' => $thumbnailUrl,
        ];

        // uploadDate برای Google الزامی است — برای *همه‌ی* VideoObjectها، نه فقط فایل‌ها. فایلِ
        // خودمیزبان → created_atِ Media؛ وگرنه (از جمله embedِ یوتیوب/ویمئو) → fallbackِ سطحِ صفحه‌ی
        // اصلی (زمانِ آخرین ذخیره‌ی تنظیماتِ ویدیو) تا هیچ ویدیویی بدونِ uploadDate منتشر نشود.
        if ($uploadDate = ($media?->created_at?->toIso8601String() ?: $fallbackUploadDate)) {
            $schema['uploadDate'] = $uploadDate;
        }

        if ($contentUrl) {
            $schema['contentUrl'] = $contentUrl;
            // duration فقط برای فایلِ خودمیزبان که مدتش هنگامِ آپلود خوانده شده — یوتیوب/ویمئو منبعِ
            // مطمئنی بدونِ API ندارند، پس عمداً بدونِ duration می‌مانند (H2)
            if ($duration = $media?->duration_iso8601) {
                $schema['duration'] = $duration;
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

    private function mediaForDiskPath(string $filePath): ?Media
    {
        return Media::where('disk_path', $filePath)->first();
    }

    // contentUrl (مطلق یا /storage/...) → disk_pathِ ردیفِ Media — همان قراردادِ مسیرِ خامِ DAM
    // (پیشوندِ عمومیِ /storage/ حذف می‌شود). null اگر مسیرِ معتبری در نیاید.
    private function diskPathFromContentUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }

    // fallbackِ تامبنیلِ سراسری — یک‌بار خوانده و کش می‌شود (SiteSetting یک کوئری، فقط هنگامِ نیاز)
    private function defaultThumbnail(): ?string
    {
        if (! $this->defaultThumbLoaded) {
            $this->defaultThumbLoaded = true;
            $path = SiteSetting::get('about.en.hero_image');
            $this->defaultThumb = filled($path) ? asset('storage/'.ltrim((string) $path, '/')) : null;
        }

        return $this->defaultThumb;
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
