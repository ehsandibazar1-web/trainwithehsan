<?php

namespace App\Services\ArticleImport;

use App\Models\Article;
use App\Models\ImportLog;
use App\Models\InternalLinkSuggestion;
use App\Models\Media;
use App\Models\Page;
use App\Models\Tag;
use App\Services\Media\MediaProcessor;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * سرویس واردکردن مقاله‌ی تولیدشده با هوش مصنوعی.
 *
 * این سرویس عمداً از UI جداست: پنل Filament (صفحه‌ی AI Import) امروز و
 * API امن آینده هر دو باید از همین analyze()/import() استفاده کنند —
 * منطق نگاشت/اعتبارسنجی را در لایه‌ی UI تکرار نکنید.
 *
 * analyze() هیچ چیزی ذخیره نمی‌کند (پیش‌نمایش/اعتبارسنجی امن)؛
 * فقط import() می‌نویسد و همان هم نتیجه را در import_logs ثبت می‌کند.
 */
class ArticleImportService
{
    // نام‌های جایگزینِ پذیرفته‌شده برای هر فیلد استاندارد — افزودن فیلد آینده = یک ردیف اینجا + شاخه‌ی نگاشت
    private const ALIASES = [
        'locale' => ['language', 'locale', 'lang'],
        'title' => ['title'],
        'slug' => ['slug'],
        'excerpt' => ['excerpt', 'summary'],
        'body' => ['content', 'body'],
        'body_format' => ['content_format', 'body_format'],
        'featured_image' => ['featured_image', 'image', 'image_path'],
        'category' => ['category'],
        'tags' => ['tags'],
        'faqs' => ['faq', 'faqs'],
        'seo' => ['seo'],
        'og' => ['og', 'open_graph'],
        'canonical' => ['canonical'],
        'robots' => ['robots'],
        'schema' => ['schema', 'structured_data'],
        'published_at' => ['publish_date', 'published_at', 'date'],
        'status' => ['publish_status', 'status'],
        'author_name' => ['author', 'author_name'],
        'reading_time' => ['reading_time'],
        'translation_of' => ['translation_of'],
        'provider' => ['provider', 'ai_provider'],
        'image_alt' => ['image_alt', 'alt', 'alt_text'],
        'image_caption' => ['image_caption', 'caption'],
        'featured_image_prompt' => ['featured_image_prompt', 'image_prompt'],
        'cta' => ['cta', 'call_to_action'],
        'twitter' => ['twitter', 'twitter_card'],
        'keywords' => ['keywords', 'seo_keywords'],
        // نسخه‌ی مسطحِ این چهار فیلد — نه برای محتوای خام هوش مصنوعی (که همچنان seo.title/seo.meta_description/og.title/og.description
        // تودرتو تولید می‌کند)، بلکه برای لایه‌ی $overrides «اصلاح دستی» در پنل که مقادیر مسطح می‌فرستد
        'seo_title' => ['seo_title'],
        'meta_description' => ['meta_description'],
        'og_title' => ['og_title'],
        'og_description' => ['og_description'],
        'internal_links' => ['internal_links', 'internal_link_suggestions'],
        'external_links' => ['external_links', 'external_link_suggestions'],
    ];

    private const IMAGE_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    // فرمت‌هایی که analyze() امروز خودکار تشخیص می‌دهد — افزودن فرمت تازه = یک شاخه‌ی دیگر در
    // detectFormat() + یک متد parseX جدید که همان شکل آرایه‌ی JSON را برمی‌گرداند تا وارد همان
    // normalizeAndValidate() موجود (دست‌نخورده) بشود؛ هیچ فرمتی مسیر اعتبارسنجی جداگانه ندارد
    private const FORMATS = ['json', 'xml', 'custom', 'html', 'markdown'];

    /**
     * تجزیه + نرمال‌سازی + اعتبارسنجی، بدون هیچ ذخیره‌سازی.
     *
     * $defaults (مثلاً از یک پروفایل هوش مصنوعی) فقط جاهای خالی محتوا را پر می‌کند —
     * مقداری که خود محتوا داشته باشد همیشه برنده است. $overrides برعکس: همیشه برنده می‌شود،
     * حتی روی مقدار غیرخالیِ محتوا — پایه‌ی «اصلاح دستی» در پنل ادمین پیش از Import نهایی.
     *
     * @return array{payload: ?array, errors: string[], warnings: string[], mapping: array{mapped: array<string,string>, auto: array<string,string>, skipped: array<string,string>}, format: string}
     */
    public function analyze(string $raw, string $format = 'auto', array $defaults = [], array $overrides = []): array
    {
        $result = [
            'payload' => null,
            'errors' => [],
            'warnings' => [],
            'mapping' => ['mapped' => [], 'auto' => [], 'skipped' => []],
            'format' => $format,
        ];

        $raw = trim($raw);
        if ($raw === '') {
            $result['errors'][] = 'Nothing to import — paste the AI output first.';

            return $result;
        }

        if ($format === 'auto' || ! in_array($format, self::FORMATS, true)) {
            $format = $this->detectFormat($raw);
        }
        $result['format'] = $format;

        [$input, $parseErrors] = match ($format) {
            'json' => $this->parseJson($raw),
            'xml' => $this->parseXml($raw),
            'custom' => $this->parseCustomMarkers($raw),
            'html' => $this->parseHtml($raw),
            default => $this->parseMarkdown($raw),
        };

        if ($parseErrors !== []) {
            $result['errors'] = $parseErrors;

            return $result;
        }

        return $this->normalizeAndValidate($input, $defaults, $overrides) + ['format' => $format];
    }

    /**
     * مانند analyze() اما اجرای پیش‌نمایش را در تاریخچه (import_logs) با
     * وضعیت previewed ثبت می‌کند. همچنان هیچ مقاله‌ای ساخته نمی‌شود.
     *
     * @return array{payload: ?array, errors: string[], warnings: string[], mapping: array, format: string, log: ImportLog}
     */
    public function preview(string $raw, string $format = 'auto', array $context = [], array $defaults = [], array $overrides = []): array
    {
        $analysis = $this->analyze($raw, $format, $defaults, $overrides);
        $analysis['log'] = $this->writeLog('previewed', $analysis, null, $context);

        return $analysis;
    }

    /**
     * تشخیص خودکار فرمت — JSON/XML به‌کمک نویسه‌ی آغازین، فرمت سفارشیِ نشانه‌گذار [[FIELD]]
     * به‌کمک الگوی خط اول، HTML به‌کمک تگ آغازین، و مارک‌داون به‌عنوان پیش‌فرضِ باقی‌مانده.
     */
    private function detectFormat(string $raw): string
    {
        $trimmed = ltrim($raw);

        // باید پیش از بررسی JSON بیاید — نشانه‌ی [[FIELD]] هم با یک [ آغاز می‌شود
        if (preg_match('/^\[\[[A-Z_]+\]\]/m', $trimmed)) {
            return 'custom';
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return 'json';
        }

        if (str_starts_with($trimmed, '<?xml') || preg_match('/^<article[\s>]/i', $trimmed)) {
            return 'xml';
        }

        if (str_starts_with($trimmed, '<')) {
            return 'html';
        }

        return 'markdown';
    }

    /**
     * بازگردانی یک ایمپورت موفق: مقاله‌ی ساخته‌شده حذف می‌شود (از طریق Eloquent
     * تا Activity Log هم ثبت کند) و زمان/کاربر بازگردانی روی لاگ می‌ماند.
     * فایل رسانه‌ی دانلودشده عمداً حذف نمی‌شود — ممکن است جای دیگری استفاده شده باشد.
     *
     * @return array{ok: bool, message: string}
     */
    public function rollback(ImportLog $log, array $context = []): array
    {
        if (! $log->canRollBack()) {
            return ['ok' => false, 'message' => 'This import cannot be rolled back — it either failed, was already rolled back, or its article no longer exists.'];
        }

        $log->article->delete();

        $log->update([
            'rolled_back_at' => now(),
            'rolled_back_by' => $context['user_id'] ?? auth()->id(),
        ]);

        return ['ok' => true, 'message' => 'The imported article "'.$log->article_title.'" was removed.'];
    }

    /**
     * اجرای کامل ایمپورت. اگر اعتبارسنجی خطا داشته باشد چیزی ساخته نمی‌شود،
     * ولی نتیجه (موفق یا ناموفق) همیشه در import_logs ثبت می‌شود.
     *
     * @param  array{user_id?: ?int, source?: string}  $context
     * @return array{article: ?Article, errors: string[], warnings: string[], log: ImportLog}
     */
    public function import(string $raw, string $format = 'auto', array $context = [], array $defaults = [], array $overrides = []): array
    {
        $analysis = $this->analyze($raw, $format, $defaults, $overrides);
        $payload = $analysis['payload'];

        if ($analysis['errors'] !== [] || $payload === null) {
            $log = $this->writeLog('failed', $analysis, null, $context);

            return ['article' => null, 'errors' => $analysis['errors'], 'warnings' => $analysis['warnings'], 'log' => $log];
        }

        try {
            $imageCount = 0;
            $media = null;

            // تصویر شاخص: URL جدید → دانلود به کتابخانه‌ی رسانه (از طریق همان MediaProcessor که
            // بقیه‌ی مسیرهای آپلود در این پروژه استفاده می‌کنند تا WebP/تامبنیل/سایزهای واکنش‌گرا
            // هم برای تصاویر ایمپورت‌شده تولید شود)؛ مسیر موجود → استفاده‌ی مجدد از ردیف Media فعلی
            if (($payload['featured_image'] ?? null) && $this->isUrl($payload['featured_image'])) {
                $media = $this->downloadImage($payload['featured_image']);
                $payload['image_path'] = $media->disk_path;
            } elseif ($payload['featured_image'] ?? null) {
                $payload['image_path'] = $payload['featured_image'];
                $media = Media::where('disk_path', $payload['featured_image'])->first();
            }
            if (! empty($payload['image_path'])) {
                $imageCount = 1;
            }
            if ($media && ! empty($payload['image_alt'])) {
                $media->update(['alt_text' => $payload['image_alt']]);
            }

            // ساخت از طریق Eloquent تا Activity Log (اسپاتی) به‌طور خودکار ثبت شود
            $article = Article::create([
                'locale' => $payload['locale'],
                'translation_of' => $payload['translation_of'] ?? null,
                'title' => $payload['title'],
                'slug' => $payload['slug'],
                'category' => $payload['category'] ?? null,
                'excerpt' => $payload['excerpt'] ?? null,
                'seo_title' => $payload['seo_title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'body' => $payload['body'],
                'og_title' => $payload['og_title'] ?? null,
                'og_description' => $payload['og_description'] ?? null,
                'faqs' => $payload['faqs'] ?? null,
                'image_path' => $payload['image_path'] ?? null,
                'author_name' => $payload['author_name'],
                'reading_time' => $payload['reading_time'],
                'status' => $payload['status'],
                'published_at' => $payload['published_at'],
            ]);

            if (! empty($payload['tags'])) {
                $tagIds = collect($payload['tags'])->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id);
                $article->tags()->sync($tagIds);
            }

            foreach ($payload['keywords'] ?? [] as $keyword) {
                $article->keywords()->create(['keyword' => $keyword]);
            }

            // پیشنهادهای لینک داخلیِ اعلام‌شده توسط هوش مصنوعی — دقیقاً همان جدول/چرخه‌ی موجود
            // (pending/origin=ai)، هرگز مسیر درج جداگانه‌ای نه؛ نگاه کنید به Internal Linking Center
            foreach ($payload['internal_links'] ?? [] as $link) {
                $this->createInternalLinkSuggestion($article, $link);
            }

            $log = $this->writeLog('imported', $analysis, $article, $context, $imageCount);

            return ['article' => $article, 'errors' => [], 'warnings' => $analysis['warnings'], 'log' => $log];
        } catch (Throwable $e) {
            $analysis['errors'][] = 'Import failed: '.$e->getMessage();
            $log = $this->writeLog('failed', $analysis, null, $context);

            return ['article' => null, 'errors' => $analysis['errors'], 'warnings' => $analysis['warnings'], 'log' => $log];
        }
    }

    // یک آیتم internal_links (شکل نرمال‌شده در normalizeAndValidate) را به یک ردیف pending/origin=ai
    // در همان جدول internal_link_suggestions تبدیل می‌کند — هدف را با اسلاگ (بین Article و Page) یا id پیدا می‌کند
    private function createInternalLinkSuggestion(Article $source, array $link): void
    {
        $target = $this->resolveLinkTarget($link['target'] ?? '');

        if (! $target) {
            return;
        }

        InternalLinkSuggestion::updateOrCreate(
            [
                'source_type' => 'Article',
                'source_id' => $source->id,
                'target_type' => $target::class === Page::class ? 'Page' : 'Article',
                'target_id' => $target->id,
            ],
            [
                'locale' => $source->locale,
                'confidence_score' => 70,
                'recommended_anchor_text' => Str::limit($link['anchor_text'] ?: $target->title, 60, ''),
                'reason' => $link['reason'] ?: 'Suggested by the imported AI content.',
                'status' => 'pending',
                'origin' => 'ai',
            ]
        );
    }

    private function resolveLinkTarget(string $ref): Article|Page|null
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (is_numeric($ref)) {
            return Article::find((int) $ref) ?? Page::find((int) $ref);
        }

        return Article::where('slug', $ref)->first() ?? Page::where('slug', $ref)->first();
    }

    // ---------------------------------------------------------------- parsing

    /** @return array{0: array, 1: string[]} */
    private function parseJson(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [[], ['Invalid JSON: '.json_last_error_msg().'.']];
        }

        return [$decoded, []];
    }

    /**
     * مارک‌داون با سرآیند (front matter) ساده‌ی key: value بین دو خط «---».
     * کلیدهای نقطه‌دار (seo.meta_description) تودرتو می‌شوند؛ بخش «## FAQ» در
     * انتهای متن به پرسش‌های ### تبدیل و از بدنه جدا می‌شود.
     *
     * @return array{0: array, 1: string[]}
     */
    private function parseMarkdown(string $raw): array
    {
        $meta = [];
        $body = $raw;

        if (preg_match('/\A---\s*\R(.*?)\R---\s*\R?(.*)\z/s', $raw, $m)) {
            [$meta, $errors] = $this->parseKeyValueBlock($m[1]);
            if ($errors !== []) {
                return [[], $errors];
            }
            $body = $m[2];
        }

        if (preg_match('/^##\s+FAQ\s*$/mi', $body, $mm, PREG_OFFSET_CAPTURE)) {
            $faqMd = substr($body, $mm[0][1] + strlen($mm[0][0]));
            $body = substr($body, 0, $mm[0][1]);

            $faqs = [];
            foreach (preg_split('/^###\s+/m', trim($faqMd)) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $pieces = preg_split('/\R/', $part, 2);
                $faqs[] = ['question' => trim($pieces[0]), 'answer' => trim($pieces[1] ?? '')];
            }
            if ($faqs !== []) {
                $meta['faq'] = $faqs;
            }
        }

        $meta['content'] = trim($body);
        $meta['content_format'] = 'markdown';

        return [$meta, []];
    }

    /**
     * تجزیه‌ی یک بلوکِ سطرهای «field: value» — هم برای front matter مارک‌داون (بین دو خط ---)
     * و هم برای بلوک کامنتِ ابتداییِ HTML (<!-- ... -->) استفاده می‌شود تا این منطق یک‌بار نوشته شود.
     * کلیدهای نقطه‌دار (seo.meta_description) تودرتو می‌شوند.
     *
     * @return array{0: array, 1: string[]}
     */
    private function parseKeyValueBlock(string $block): array
    {
        $meta = [];
        $errors = [];

        foreach (preg_split('/\R/', $block) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, ':')) {
                $errors[] = "Metadata line is not in \"field: value\" form: \"$line\".";

                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            data_set($meta, trim($key), trim(trim($value), '"\''));
        }

        return [$meta, $errors];
    }

    /**
     * محتوای HTML — سند کاملِ HTML (با <title>/<meta description>/<meta og:*> و <body>) یا یک
     * قطعه‌ی HTML ساده که کل آن بدنه‌ی مقاله است. یک بلوکِ کامنتِ ابتدایی (<!-- field: value -->)
     * به‌عنوان متادیتای اختیاری پذیرفته می‌شود — همان قالب key:value که در Markdown هست.
     *
     * @return array{0: array, 1: string[]}
     */
    private function parseHtml(string $raw): array
    {
        $meta = [];
        $body = $raw;

        if (preg_match('/\A\s*<!--\s*\R(.*?)\R\s*-->\s*\R?(.*)\z/s', $raw, $m)) {
            [$meta, $errors] = $this->parseKeyValueBlock($m[1]);
            if ($errors !== []) {
                return [[], $errors];
            }
            $body = $m[2];
        }

        if (! isset($meta['title']) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $raw, $m)) {
            $meta['title'] = trim(html_entity_decode(strip_tags($m[1])));
        }
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $raw, $m)) {
            data_set($meta, 'seo.meta_description', trim(html_entity_decode($m[1])));
        }
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/is', $raw, $m)) {
            data_set($meta, 'og.title', trim(html_entity_decode($m[1])));
        }
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/is', $raw, $m)) {
            data_set($meta, 'og.description', trim(html_entity_decode($m[1])));
        }

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $body, $m)) {
            $body = $m[1];
        }

        $meta['content'] = trim($body);
        $meta['content_format'] = 'html';

        return [$meta, []];
    }

    /**
     * XML سفارشی این پروژه: یک ریشه‌ی <article> با فرزندانی هم‌نام کلیدهای استاندارد
     * (title/slug/content/seo/og/faq/tags/internal_links/external_links/...).
     *
     * @return array{0: array, 1: string[]}
     */
    private function parseXml(string $raw): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);

        if ($xml === false) {
            $messages = collect(libxml_get_errors())->map(fn ($e) => trim($e->message))->unique()->values()->all();
            libxml_clear_errors();

            return [[], $messages !== [] ? ['Invalid XML: '.implode(' ', $messages)] : ['Invalid XML.']];
        }

        $meta = [
            'language' => (string) ($xml->language ?? ''),
            'title' => (string) ($xml->title ?? ''),
            'slug' => (string) ($xml->slug ?? ''),
            'excerpt' => (string) ($xml->excerpt ?? ''),
            'content' => (string) ($xml->content ?? ''),
            'content_format' => (string) ($xml->content['format'] ?? 'html'),
            'category' => (string) ($xml->category ?? ''),
            'featured_image' => (string) ($xml->featured_image ?? ''),
            'image_alt' => (string) ($xml->image_alt ?? ''),
            'image_caption' => (string) ($xml->image_caption ?? ''),
            'featured_image_prompt' => (string) ($xml->featured_image_prompt ?? ''),
            'publish_status' => (string) ($xml->status ?? ''),
            'publish_date' => (string) ($xml->publish_date ?? ''),
            'author' => (string) ($xml->author ?? ''),
            'reading_time' => (string) ($xml->reading_time ?? ''),
            'translation_of' => (string) ($xml->translation_of ?? ''),
            'provider' => (string) ($xml->provider ?? ''),
            'cta' => (string) ($xml->cta ?? ''),
        ];

        // xpath() همیشه یک آرایه‌ی معمولی برمی‌گرداند (حتی برای صفر یا یک تطبیق) — برخلاف
        // دسترسی جادوییِ $parent->child که وقتی دقیقاً یک عنصر تطبیق دارد، خودِ همان عنصر را
        // برمی‌گرداند (نه یک مجموعه)، و وقتی چند عنصرِ هم‌نام دارد، iterator_to_array() روی آن با
        // کلیدهای تکراری (نام تگ) رونویسی می‌کند و فقط آخرین مورد باقی می‌ماند — هر دو باگ واقعی
        // که در توسعه‌ی این ویژگی رخ داد
        if (isset($xml->tags)) {
            $meta['tags'] = collect($xml->xpath('tags/tag'))->map(fn ($t) => trim((string) $t))->filter()->values()->all();
        }

        if (isset($xml->faq)) {
            $meta['faq'] = collect($xml->xpath('faq/item'))->map(fn ($item) => [
                'question' => trim((string) ($item->question ?? '')),
                'answer' => trim((string) ($item->answer ?? '')),
            ])->all();
        }

        if (isset($xml->seo)) {
            $seo = [
                'title' => (string) ($xml->seo->title ?? ''),
                'meta_description' => (string) ($xml->seo->meta_description ?? ''),
            ];
            if (isset($xml->seo->keywords)) {
                $seo['keywords'] = collect($xml->xpath('seo/keywords/keyword'))->map(fn ($k) => trim((string) $k))->filter()->values()->all();
            }
            $meta['seo'] = $seo;
        }

        if (isset($xml->og)) {
            $meta['og'] = ['title' => (string) ($xml->og->title ?? ''), 'description' => (string) ($xml->og->description ?? '')];
        }

        if (isset($xml->internal_links)) {
            $meta['internal_links'] = collect($xml->xpath('internal_links/link'))->map(fn ($l) => [
                'target' => (string) ($l['slug'] ?? $l['id'] ?? ''),
                'anchor_text' => (string) ($l['anchor'] ?? ''),
                'reason' => (string) ($l['reason'] ?? ''),
            ])->all();
        }

        if (isset($xml->external_links)) {
            $meta['external_links'] = collect($xml->xpath('external_links/link'))->map(fn ($l) => [
                'target' => (string) ($l['url'] ?? ''),
                'anchor_text' => (string) ($l['anchor'] ?? ''),
                'reason' => (string) ($l['reason'] ?? ''),
            ])->all();
        }

        // کلیدهای رشته‌ای خالی حذف می‌شوند تا با «فیلد داده نشده» در بقیه‌ی پایپ‌لاین یکسان رفتار شوند
        $meta = array_filter($meta, fn ($v) => $v !== '' && $v !== null);

        return [$meta, []];
    }

    /**
     * فرمت سفارشیِ نشانه‌گذار: بلوک‌های [[FIELD_NAME]] که تا نشانه‌ی بعدی ادامه می‌یابند — طراحی‌شده
     * برای «قالب دلخواه هوش مصنوعی» وقتی الگوی خاصی از سوی کاربر خواسته نشده. FAQ به‌صورت جفت‌های
     * «Q: ... / A: ...»، لینک‌ها به‌صورت یک مورد در هر خط با جداکننده‌ی | (هدف | متن لنگر | دلیل).
     *
     * @return array{0: array, 1: string[]}
     */
    private function parseCustomMarkers(string $raw): array
    {
        preg_match_all('/^\[\[([A-Z_]+)\]\]\s*\R(.*?)(?=^\[\[[A-Z_]+\]\]|\z)/ms', $raw, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return [[], ['No recognizable [[FIELD]] markers were found — wrap each field in double brackets, e.g. [[TITLE]].']];
        }

        $meta = [];
        foreach ($matches as $match) {
            $key = strtolower($match[1]);
            $value = trim($match[2]);

            if ($value === '') {
                continue;
            }

            match ($key) {
                'faq' => $meta['faq'] = $this->parseQaBlock($value),
                'tags' => $meta['tags'] = array_values(array_filter(array_map('trim', explode(',', $value)))),
                'keywords' => data_set($meta, 'seo.keywords', array_values(array_filter(array_map('trim', explode(',', $value))))),
                'seo_title' => data_set($meta, 'seo.title', $value),
                'meta_description' => data_set($meta, 'seo.meta_description', $value),
                'og_title' => data_set($meta, 'og.title', $value),
                'og_description' => data_set($meta, 'og.description', $value),
                'internal_links' => $meta['internal_links'] = $this->parseLinkLines($value),
                'external_links' => $meta['external_links'] = $this->parseLinkLines($value),
                'body', 'content' => $meta['content'] = $value,
                default => $meta[$key] = $value,
            };
        }

        return [$meta, []];
    }

    /** @return array{question: string, answer: string}[] */
    private function parseQaBlock(string $text): array
    {
        preg_match_all('/Q:\s*(.+?)\R+A:\s*(.+?)(?=\R+Q:|\z)/s', $text, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn ($m) => ['question' => trim($m[1]), 'answer' => trim($m[2])])
            ->filter(fn (array $qa) => $qa['question'] !== '' && $qa['answer'] !== '')
            ->values()
            ->all();
    }

    /** @return array{target: string, anchor_text: string, reason: string}[] */
    private function parseLinkLines(string $text): array
    {
        $links = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $links[] = [
                'target' => $parts[0] ?? '',
                'anchor_text' => $parts[1] ?? '',
                'reason' => $parts[2] ?? '',
            ];
        }

        return $links;
    }

    // ------------------------------------------------- normalization + validation

    /** @return array{payload: ?array, errors: string[], warnings: string[], mapping: array} */
    private function normalizeAndValidate(array $input, array $defaults = [], array $overrides = []): array
    {
        $errors = [];
        $warnings = [];
        $mapping = ['mapped' => [], 'auto' => [], 'skipped' => []];

        // ترجمه‌ی نام‌های جایگزین به کلیدهای استاندارد + هشدار برای فیلدهای ناشناخته (نه خطا — قالب باید توسعه‌پذیر بماند)
        [$data, $unknownKeys] = $this->resolveAliases($input);
        foreach ($unknownKeys as $unknown) {
            $warnings[] = "Unknown field \"$unknown\" was ignored.";
        }

        // پیش‌فرض‌های پروفایل فقط جاهای خالی را پر می‌کنند — محتوای واردشده همیشه مقدم است
        [$defaultData] = $this->resolveAliases($defaults);
        foreach ($defaultData as $key => $value) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                $data[$key] = $value;
                $warnings[] = "Profile default applied for \"$key\": $value.";
            }
        }

        // اصلاح‌های دستیِ ادمین در پنل — برخلاف پیش‌فرض‌ها، همیشه برنده می‌شوند (حتی روی مقدار
        // غیرخالیِ محتوای واردشده)؛ چون این‌جا نتیجه‌ی یک ویرایش آگاهانه است، نه یک مقدار جای‌خالی
        [$overrideData] = $this->resolveAliases($overrides);
        foreach ($overrideData as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $data[$key] = $value;
            }
        }

        // ----- زبان
        $locale = strtolower(trim((string) ($data['locale'] ?? '')));
        if ($locale === '') {
            $locale = 'en';
            $warnings[] = 'No language given — defaulted to English.';
        }
        if (! in_array($locale, ['en', 'tr'], true)) {
            $errors[] = "Invalid language \"$locale\" — must be \"en\" or \"tr\".";
        }
        $mapping['mapped']['language'] = "Article language ($locale)";

        // ----- عنوان و بدنه
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Missing title.';
        } else {
            $mapping['mapped']['title'] = 'Article title';
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            $errors[] = 'Missing content.';
        } else {
            if (strtolower((string) ($data['body_format'] ?? 'html')) === 'markdown') {
                $body = (string) Str::markdown($body);
            }
            $mapping['mapped']['content'] = 'Article body';
        }

        // ----- اسلاگ (در صورت نبود، از عنوان ساخته می‌شود — همان الگوی فرم مقاله)
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Article::makeSlug($title);
        } elseif (! preg_match('/^[A-Za-z0-9-]+$/', $slug)) {
            $errors[] = "Invalid slug \"$slug\" — only letters, numbers and dashes are allowed.";
        }
        if ($slug !== '' && in_array($locale, ['en', 'tr'], true)
            && Article::locale($locale)->where('slug', $slug)->exists()) {
            $errors[] = "An article with the slug \"$slug\" already exists for this language.";
        }
        $mapping['mapped']['slug'] = "Article URL (/blog/$slug)";

        // ----- خلاصه (کارت بلاگ/RSS) — مستقل از seo.meta_description از این‌جا به بعد، چون آن حالا
        // ستون meta_description واقعی خودش را دارد (Section 23)؛ اگر خلاصه خالی باشد همچنان از
        // توضیح سئو به‌عنوان جایگزین استفاده می‌شود (رفتار قبلی حفظ شده)
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $seo = is_array($data['seo'] ?? null) ? $data['seo'] : [];
        $og = is_array($data['og'] ?? null) ? $data['og'] : [];
        $seoDescription = trim((string) ($data['meta_description'] ?? $seo['meta_description'] ?? $seo['description'] ?? ''));

        if ($excerpt !== '') {
            $mapping['mapped']['excerpt'] = 'Excerpt (shown on the blog list card and used as the RSS description)';
        } elseif ($seoDescription !== '') {
            $excerpt = $seoDescription;
            $warnings[] = 'No excerpt given — reused the SEO meta description as the excerpt too.';
        } else {
            $warnings[] = 'No excerpt or SEO description given — the meta description will be derived from the first part of the content.';
        }

        // ----- عنوان/توضیح سئو و Open Graph — ستون‌های واقعی روی articles (Section 23)؛ خالی
        // ماندن یعنی الگوی عمومی سایت (title/excerpt) همچنان به‌کار می‌رود، دقیقاً مثل امروز
        $seoTitle = trim((string) ($data['seo_title'] ?? $seo['title'] ?? ''));
        if ($seoTitle !== '') {
            $mapping['mapped']['seo.title'] = 'SEO title (overrides the article title in search results)';
            if (mb_strlen($seoTitle) > 70) {
                $warnings[] = 'The SEO title is longer than the recommended 70 characters and may be truncated by Google.';
            }
        }
        if ($seoDescription !== '') {
            $mapping['mapped']['seo.meta_description'] = 'Meta description (overrides the excerpt in search results)';
        }

        $ogTitle = trim((string) ($data['og_title'] ?? $og['title'] ?? ''));
        $ogDescription = trim((string) ($data['og_description'] ?? $og['description'] ?? ''));
        if ($ogTitle !== '') {
            $mapping['mapped']['og.title'] = 'Open Graph title (social share preview)';
        }
        if ($ogDescription !== '') {
            $mapping['mapped']['og.description'] = 'Open Graph description (social share preview)';
        }
        if ($ogTitle === '' && $ogDescription === '' && array_key_exists('og', $data)) {
            $mapping['auto']['og'] = 'Open Graph tags are generated automatically from the title/excerpt when not explicitly provided.';
        }

        // ----- فیلدهایی که سیستم سئوی موجود همچنان خودش به‌طور خودکار می‌سازد (بدون امکان override)
        foreach (['canonical' => 'Canonical URL', 'robots' => 'Robots meta'] as $field => $label) {
            if (array_key_exists($field, $data)) {
                $mapping['auto'][$field] = "$label is generated automatically by the existing SEO system — there is no per-page override on this site yet.";
            }
        }
        if (array_key_exists('schema', $data)) {
            $mapping['auto']['schema'] = 'Article structured data (JSON-LD) is generated automatically by the existing SEO system — a custom schema override is not supported yet.';
        }

        // ----- فیلدهایی که هنوز جایی در این CMS ندارند — فقط برای مرجع در پیش‌نمایش نشان داده می‌شوند
        foreach ([
            'image_caption' => 'Image caption has no storage in this CMS yet — shown here for reference only.',
            'cta' => 'Call-to-action text has no dedicated field in this CMS yet — copy it into the article body manually if needed.',
            'twitter' => 'Twitter Card meta tags are not wired into the public templates yet.',
        ] as $field => $reason) {
            if (array_key_exists($field, $data) && trim((string) (is_array($data[$field]) ? json_encode($data[$field]) : $data[$field])) !== '') {
                $mapping['skipped'][$field] = $reason;
            }
        }
        if (array_key_exists('featured_image_prompt', $data) && trim((string) $data['featured_image_prompt']) !== '') {
            $mapping['skipped']['featured_image_prompt'] = 'This is a prompt for generating an image, not an image itself — generate the image separately and provide it via featured_image.';
        }

        // ----- برچسب‌ها — روی Article::tags() واقعی می‌نشینند (import() آن‌ها را می‌سازد/وصل می‌کند)
        $tags = [];
        if (array_key_exists('tags', $data)) {
            $tags = collect(is_array($data['tags']) ? $data['tags'] : explode(',', (string) $data['tags']))
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($tags !== []) {
                $mapping['mapped']['tags'] = count($tags).' tag(s): '.implode(', ', $tags);
            }
        }

        // ----- کلیدواژه‌های هدف سئو — روی Article::keywords() واقعی می‌نشینند (Internal Linking Center)
        $keywords = [];
        $rawKeywords = $data['keywords'] ?? $seo['keywords'] ?? null;
        if ($rawKeywords !== null) {
            $keywords = collect(is_array($rawKeywords) ? $rawKeywords : explode(',', (string) $rawKeywords))
                ->map(fn ($k) => trim((string) $k))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($keywords !== []) {
                $mapping['mapped']['seo.keywords'] = count($keywords).' target keyword(s): '.implode(', ', $keywords);
            }
        }

        // ----- دسته
        $category = trim((string) ($data['category'] ?? ''));
        if ($category !== '') {
            $mapping['mapped']['category'] = 'Article category';
        }

        // ----- پرسش‌های متداول
        $faqs = null;
        if (array_key_exists('faqs', $data)) {
            if (! is_array($data['faqs'])) {
                $errors[] = 'Invalid FAQ — it must be a list of question/answer pairs.';
            } else {
                $faqs = [];
                foreach (array_values($data['faqs']) as $i => $item) {
                    $q = trim((string) ($item['question'] ?? $item['q'] ?? ''));
                    $a = trim((string) ($item['answer'] ?? $item['a'] ?? ''));
                    if ($q === '' || $a === '') {
                        $errors[] = 'Invalid FAQ item #'.($i + 1).' — both a question and an answer are required.';

                        continue;
                    }
                    $faqs[] = ['question' => $q, 'answer' => $a];
                }
                if ($faqs === []) {
                    $faqs = null;
                }
            }
            if ($faqs !== null) {
                $mapping['mapped']['faq'] = 'FAQ section ('.count($faqs).' questions, with automatic FAQ schema)';
            }
        }

        // ----- تصویر شاخص
        $featuredImage = null;
        $rawImage = $data['featured_image'] ?? null;
        if (is_array($rawImage)) {
            $rawImage = $rawImage['url'] ?? $rawImage['path'] ?? null;
        }
        $rawImage = trim((string) $rawImage);
        if ($rawImage !== '') {
            if ($this->isUrl($rawImage)) {
                if (! filter_var($rawImage, FILTER_VALIDATE_URL)) {
                    $errors[] = "Invalid image URL \"$rawImage\".";
                } else {
                    $featuredImage = $rawImage;
                    $mapping['mapped']['featured_image'] = 'Featured image (will be downloaded into the media library)';
                }
            } elseif (! Storage::disk('public')->exists($rawImage)) {
                $errors[] = "Image \"$rawImage\" was not found in the media library.";
            } else {
                $featuredImage = $rawImage;
                $mapping['mapped']['featured_image'] = 'Featured image (existing media file, reused)';
            }
        }

        // ----- متن جایگزین تصویر — روی همان ردیف Media نشسته می‌شود (import() این کار را می‌کند)
        $imageAlt = trim((string) ($data['image_alt'] ?? ''));
        if ($imageAlt !== '') {
            if ($featuredImage !== null) {
                $mapping['mapped']['image_alt'] = 'ALT text for the featured image';
            } else {
                $mapping['skipped']['image_alt'] = 'No featured image was provided, so there is nothing to attach ALT text to.';
            }
        }

        // ----- پیشنهادهای لینک داخلی — به‌عنوان ردیف pending/origin=ai در همان جدول موجود ذخیره
        // می‌شوند (نگاه کنید به import())؛ اینجا فقط شکل‌شان اعتبارسنجی می‌شود
        $internalLinks = [];
        if (array_key_exists('internal_links', $data) && is_array($data['internal_links'])) {
            foreach ($data['internal_links'] as $link) {
                $target = trim((string) ($link['target'] ?? $link['slug'] ?? $link['id'] ?? ''));
                if ($target === '') {
                    continue;
                }
                $internalLinks[] = [
                    'target' => $target,
                    'anchor_text' => trim((string) ($link['anchor_text'] ?? '')),
                    'reason' => trim((string) ($link['reason'] ?? '')),
                ];
            }
            if ($internalLinks !== []) {
                $mapping['mapped']['internal_links'] = count($internalLinks).' suggestion(s) — added as pending in the Internal Linking Center after import.';
            }
        }

        // ----- پیشنهادهای لینک خارجی — فقط برای پیش‌نمایش/مرجع؛ هیچ جدولی برایشان ذخیره نمی‌شود
        // (دقیقاً مثل فیلد external_links دستیار هوش مصنوعی — Section 23)
        $externalLinks = [];
        if (array_key_exists('external_links', $data) && is_array($data['external_links'])) {
            foreach ($data['external_links'] as $link) {
                $url = trim((string) ($link['target'] ?? $link['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $externalLinks[] = [
                    'url' => $url,
                    'anchor_text' => trim((string) ($link['anchor_text'] ?? '')),
                    'reason' => trim((string) ($link['reason'] ?? '')),
                ];
            }
            if ($externalLinks !== []) {
                $mapping['skipped']['external_links'] = count($externalLinks).' suggestion(s) — shown for reference only, paste the link into the body manually if you want to use it.';
            }
        }

        // ----- وضعیت و تاریخ انتشار — عیناً همان گردش‌کار موجود (draft | scheduled | published)
        $publishedAt = null;
        if (($data['published_at'] ?? null) !== null && trim((string) $data['published_at']) !== '') {
            try {
                $publishedAt = Carbon::parse((string) $data['published_at']);
            } catch (Throwable) {
                $errors[] = 'Invalid publish date "'.$data['published_at'].'".';
            }
        }

        $status = strtolower(trim((string) ($data['status'] ?? '')));
        if ($status === '') {
            if ($publishedAt?->isFuture()) {
                $status = 'scheduled';
                $warnings[] = 'No publish status given — a future publish date was found, so the existing scheduling system will publish it automatically at that time.';
            } elseif ($publishedAt !== null) {
                $status = 'published';
                $warnings[] = 'No publish status given — the publish date is in the past, so the article will be published with that date.';
            } else {
                $status = 'draft';
                $warnings[] = 'No publish status or date given — saved as a draft.';
            }
        }
        if (! in_array($status, ['draft', 'scheduled', 'published'], true)) {
            $errors[] = "Invalid publish status \"$status\" — must be \"draft\", \"scheduled\" or \"published\".";
        }
        if ($status === 'scheduled' && $publishedAt === null) {
            $errors[] = 'A "scheduled" article needs a publish date.';
        }
        if ($status === 'scheduled' && $publishedAt?->isPast()) {
            $warnings[] = 'The scheduled publish date is in the past — the article will be visible immediately.';
        }
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = now();
        }
        $mapping['mapped']['publish_status'] = match ($status) {
            'scheduled' => 'Scheduled — goes live automatically at '.$publishedAt?->format('Y-m-d H:i').' (existing scheduling system)',
            'published' => 'Published immediately',
            default => 'Saved as draft',
        };

        // ----- نویسنده و زمان مطالعه
        $author = trim((string) ($data['author_name'] ?? '')) ?: 'Ehsan Dibazar';
        $readingTime = (int) ($data['reading_time'] ?? 0);
        if ($readingTime < 1 && $body !== '') {
            // برآورد ساده بر مبنای ~۲۰۰ کلمه در دقیقه
            $readingTime = max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));
        }

        // ----- پیوند ترجمه (مدل دو-ردیفه‌ی موجود)
        $translationOf = null;
        if (($data['translation_of'] ?? null) !== null && trim((string) $data['translation_of']) !== '') {
            $ref = trim((string) $data['translation_of']);
            $target = is_numeric($ref)
                ? Article::find((int) $ref)
                : Article::where('slug', $ref)->first();
            if (! $target) {
                $errors[] = "translation_of refers to \"$ref\", but no article with that slug or ID exists.";
            } else {
                if ($target->locale === $locale) {
                    $warnings[] = 'translation_of points to an article in the same language — check that this is intended.';
                }
                $translationOf = $target->id;
                $mapping['mapped']['translation_of'] = "Linked as the translation of \"{$target->title}\"";
            }
        }

        $payload = [
            'locale' => $locale,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'seo_title' => $seoTitle !== '' ? $seoTitle : null,
            // اگر توضیح سئوی مستقلی داده شده باشد همان استفاده می‌شود؛ در غیر این صورت این CMS از
            // excerpt به‌عنوان توضیحات متا هم استفاده می‌کند — تا پنل ادمین و هر ابزار سئویی که
            // مستقیم این ستون را می‌خواند خالی نبیند، حتی وقتی فقط excerpt داده شده
            'meta_description' => $seoDescription !== '' ? $seoDescription : ($excerpt !== '' ? $excerpt : null),
            'body' => $body,
            'og_title' => $ogTitle !== '' ? $ogTitle : null,
            'og_description' => $ogDescription !== '' ? $ogDescription : null,
            'category' => $category !== '' ? $category : null,
            'tags' => $tags,
            'keywords' => $keywords,
            'faqs' => $faqs,
            'featured_image' => $featuredImage,
            'image_alt' => $imageAlt !== '' ? $imageAlt : null,
            'internal_links' => $internalLinks,
            'external_links' => $externalLinks,
            'status' => $status,
            'published_at' => $publishedAt,
            'author_name' => $author,
            'reading_time' => $readingTime ?: null,
            'translation_of' => $translationOf,
            'provider' => trim((string) ($data['provider'] ?? '')) ?: null,
        ];

        return [
            // payload حتی هنگام خطا برمی‌گردد تا لاگِ ایمپورتِ ناموفق هم عنوان/زبان را داشته باشد
            'payload' => $payload,
            'errors' => $errors,
            'warnings' => $warnings,
            'mapping' => $mapping,
        ];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * ترجمه‌ی نام‌های جایگزین به کلیدهای استاندارد.
     *
     * @return array{0: array, 1: string[]} [داده‌ی نرمال‌شده، کلیدهای ناشناخته]
     */
    private function resolveAliases(array $input): array
    {
        $data = [];
        $known = [];
        foreach (self::ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $known[] = $alias;
                if (array_key_exists($alias, $input) && ! array_key_exists($canonical, $data)) {
                    $data[$canonical] = $input[$alias];
                }
            }
        }

        return [$data, array_values(array_diff(array_keys($input), $known))];
    }

    private function isUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * دانلود تصویر شاخص به دیسک public + ثبت در کتابخانه‌ی رسانه (جدول media).
     * خطاها Exception می‌شوند تا import() آن‌ها را به‌صورت ایمپورت ناموفق ثبت کند.
     */
    private function downloadImage(string $url): Media
    {
        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("Could not download the image ($url) — HTTP status ".$response->status().'.');
        }

        $bytes = $response->body();
        if (strlen($bytes) > 10 * 1024 * 1024) {
            throw new \RuntimeException('The image is larger than 10 MB.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'aiimg');
        file_put_contents($tmp, $bytes);
        $info = @getimagesize($tmp);

        if (! $info || ! isset(self::IMAGE_MIME_EXT[$info['mime'] ?? ''])) {
            @unlink($tmp);

            throw new \RuntimeException('The downloaded file is not a supported image (JPEG, PNG, WebP or GIF).');
        }

        $originalName = basename(parse_url($url, PHP_URL_PATH) ?: ('image.'.self::IMAGE_MIME_EXT[$info['mime']]));

        // فایل موقتِ دانلودشده را به یک UploadedFile واقعی تبدیل می‌کند (همان الگوی استاندارد
        // Laravel برای فایل‌های ساخته‌شده به‌صورت برنامه‌نویسی‌شده — پرچم test=true) تا بتوان از
        // همان MediaProcessor استفاده کرد که هر مسیر آپلودِ دیگر در این پروژه استفاده می‌کند —
        // یعنی تصویر ایمپورت‌شده هم WebP/تامبنیل/سایزهای واکنش‌گرا و ردگیری استفاده می‌گیرد
        $uploadedFile = new UploadedFile($tmp, $originalName, $info['mime'], null, true);
        $media = app(MediaProcessor::class)->store($uploadedFile, 'articles/imported', 'public');

        @unlink($tmp);

        return $media;
    }

    private function writeLog(string $status, array $analysis, ?Article $article, array $context, int $imageCount = 0): ImportLog
    {
        return ImportLog::create([
            'user_id' => $context['user_id'] ?? auth()->id(),
            'api_token_id' => $context['api_token_id'] ?? null,
            'source' => $context['source'] ?? 'panel',
            'ai_provider' => $analysis['payload']['provider'] ?? $context['ai_provider'] ?? null,
            'format' => $analysis['format'] ?? null,
            'status' => $status,
            'errors' => $analysis['errors'],
            'warnings' => $analysis['warnings'],
            'article_id' => $article?->id,
            'article_title' => $article?->title ?? ($analysis['payload']['title'] ?? null),
            'locale' => $analysis['payload']['locale'] ?? null,
            'faq_count' => is_array($analysis['payload']['faqs'] ?? null) ? count($analysis['payload']['faqs']) : 0,
            'image_count' => $imageCount,
        ]);
    }
}
