<?php

namespace App\Services\ArticleImport;

use App\Models\Article;
use App\Models\ImportLog;
use App\Models\Media;
use Carbon\Carbon;
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
    ];

    private const IMAGE_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * تجزیه + نرمال‌سازی + اعتبارسنجی، بدون هیچ ذخیره‌سازی.
     *
     * $defaults (مثلاً از یک پروفایل هوش مصنوعی) فقط جاهای خالی محتوا را پر می‌کند —
     * مقداری که خود محتوا داشته باشد همیشه برنده است.
     *
     * @return array{payload: ?array, errors: string[], warnings: string[], mapping: array{mapped: array<string,string>, auto: array<string,string>, skipped: array<string,string>}, format: string}
     */
    public function analyze(string $raw, string $format = 'auto', array $defaults = []): array
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

        if ($format === 'auto') {
            $format = str_starts_with($raw, '{') || str_starts_with($raw, '[') ? 'json' : 'markdown';
        }
        $result['format'] = $format;

        [$input, $parseErrors] = $format === 'json'
            ? $this->parseJson($raw)
            : $this->parseMarkdown($raw);

        if ($parseErrors !== []) {
            $result['errors'] = $parseErrors;

            return $result;
        }

        return $this->normalizeAndValidate($input, $defaults) + ['format' => $format];
    }

    /**
     * مانند analyze() اما اجرای پیش‌نمایش را در تاریخچه (import_logs) با
     * وضعیت previewed ثبت می‌کند. همچنان هیچ مقاله‌ای ساخته نمی‌شود.
     *
     * @return array{payload: ?array, errors: string[], warnings: string[], mapping: array, format: string, log: ImportLog}
     */
    public function preview(string $raw, string $format = 'auto', array $context = [], array $defaults = []): array
    {
        $analysis = $this->analyze($raw, $format, $defaults);
        $analysis['log'] = $this->writeLog('previewed', $analysis, null, $context);

        return $analysis;
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
    public function import(string $raw, string $format = 'auto', array $context = [], array $defaults = []): array
    {
        $analysis = $this->analyze($raw, $format, $defaults);
        $payload = $analysis['payload'];

        if ($analysis['errors'] !== [] || $payload === null) {
            $log = $this->writeLog('failed', $analysis, null, $context);

            return ['article' => null, 'errors' => $analysis['errors'], 'warnings' => $analysis['warnings'], 'log' => $log];
        }

        try {
            $imageCount = 0;

            // تصویر شاخص: URL جدید → دانلود به کتابخانه‌ی رسانه؛ مسیر موجود → استفاده‌ی مجدد
            if (($payload['featured_image'] ?? null) && $this->isUrl($payload['featured_image'])) {
                $payload['image_path'] = $this->downloadImage($payload['featured_image'], $payload['slug']);
            } elseif ($payload['featured_image'] ?? null) {
                $payload['image_path'] = $payload['featured_image'];
            }
            if (! empty($payload['image_path'])) {
                $imageCount = 1;
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
                'body' => $payload['body'],
                'faqs' => $payload['faqs'] ?? null,
                'image_path' => $payload['image_path'] ?? null,
                'author_name' => $payload['author_name'],
                'reading_time' => $payload['reading_time'],
                'status' => $payload['status'],
                'published_at' => $payload['published_at'],
            ]);

            $log = $this->writeLog('imported', $analysis, $article, $context, $imageCount);

            return ['article' => $article, 'errors' => [], 'warnings' => $analysis['warnings'], 'log' => $log];
        } catch (Throwable $e) {
            $analysis['errors'][] = 'Import failed: '.$e->getMessage();
            $log = $this->writeLog('failed', $analysis, null, $context);

            return ['article' => null, 'errors' => $analysis['errors'], 'warnings' => $analysis['warnings'], 'log' => $log];
        }
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
        $errors = [];

        if (preg_match('/\A---\s*\R(.*?)\R---\s*\R?(.*)\z/s', $raw, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (! str_contains($line, ':')) {
                    $errors[] = "Front matter line is not in \"field: value\" form: \"$line\".";

                    continue;
                }
                [$key, $value] = explode(':', $line, 2);
                data_set($meta, trim($key), trim(trim($value), '"\''));
            }
            $body = $m[2];
        }

        if ($errors !== []) {
            return [[], $errors];
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

    // ------------------------------------------------- normalization + validation

    /** @return array{payload: ?array, errors: string[], warnings: string[], mapping: array} */
    private function normalizeAndValidate(array $input, array $defaults = []): array
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

        // ----- خلاصه + توضیح متا (در این CMS خلاصه همان منبع متا دیسکریپشن است)
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $seo = is_array($data['seo'] ?? null) ? $data['seo'] : [];
        $seoDescription = trim((string) ($seo['meta_description'] ?? $seo['description'] ?? ''));

        if ($excerpt !== '') {
            $mapping['mapped']['excerpt'] = 'Excerpt (also used as the meta description)';
            if ($seoDescription !== '') {
                $mapping['skipped']['seo.meta_description'] = 'Excerpt already provided — the excerpt is what this CMS uses as the meta description.';
            }
        } elseif ($seoDescription !== '') {
            $excerpt = $seoDescription;
            $mapping['mapped']['seo.meta_description'] = 'Excerpt (this CMS uses the excerpt as the meta description)';
        } else {
            $warnings[] = 'No excerpt or SEO description given — the meta description will be derived from the first part of the content.';
        }

        // ----- عنوان سئوی اختصاصی (اختیاری) — اگر ندهید، همچنان از عنوان مقاله ساخته می‌شود
        $seoTitle = trim((string) ($seo['title'] ?? ''));
        if ($seoTitle !== '') {
            $mapping['mapped']['seo.title'] = 'SEO title (overrides the default title-based page title)';
            if (mb_strlen($seoTitle) > 70) {
                $warnings[] = 'The SEO title is longer than the recommended 70 characters and may be truncated by Google.';
            }
        }

        // ----- فیلدهایی که سیستم سئوی موجود خودش به‌طور خودکار می‌سازد
        foreach (['og' => 'Open Graph tags', 'canonical' => 'Canonical URL', 'robots' => 'Robots meta', 'schema' => 'Article structured data (JSON-LD)'] as $field => $label) {
            if (array_key_exists($field, $data)) {
                $mapping['auto'][$field] = "$label are generated automatically by the existing SEO system — the provided value is not needed.";
            }
        }

        // ----- برچسب‌ها: فعلاً فیلدی در CMS ندارند
        if (array_key_exists('tags', $data)) {
            $mapping['skipped']['tags'] = 'This CMS has no per-article tags field yet — tags were skipped.';
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
            'body' => $body,
            'category' => $category !== '' ? $category : null,
            'faqs' => $faqs,
            'featured_image' => $featuredImage,
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
    private function downloadImage(string $url, string $slug): string
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
        @unlink($tmp);

        if (! $info || ! isset(self::IMAGE_MIME_EXT[$info['mime'] ?? ''])) {
            throw new \RuntimeException('The downloaded file is not a supported image (JPEG, PNG, WebP or GIF).');
        }

        $path = 'articles/imported/'.$slug.'-'.now()->format('YmdHis').'.'.self::IMAGE_MIME_EXT[$info['mime']];
        Storage::disk('public')->put($path, $bytes);

        Media::create([
            'original_name' => basename(parse_url($url, PHP_URL_PATH) ?: $path),
            'disk_path' => $path,
            'url' => asset('storage/'.$path),
            'type' => 'image',
            'mime_type' => $info['mime'],
            'size' => strlen($bytes),
        ]);

        return $path;
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
