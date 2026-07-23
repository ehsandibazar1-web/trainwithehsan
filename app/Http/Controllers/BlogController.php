<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Services\Seo\VideoSchemaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    // صفحه اصلی — انگلیسی (با آخرین مقالات + تنظیمات)
    public function home()
    {
        $latestArticles = Article::published()
            ->locale('en')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        [$s, $members] = $this->homeSettings('en');

        // Video SEO — داده‌ی ساختاریِ VideoObject برای ویدیوهای همین صفحه (نامرئی، افزایشی).
        // fallbackِ uploadDate: ویدیوهای embed (یوتیوب/ویمئو) تاریخِ فایل ندارند، پس زمانِ آخرین
        // ذخیره‌ی تنظیماتِ صفحه‌ی اصلی را به‌عنوان «تاریخِ انتشار روی سایت» می‌دهیم (Google الزامی می‌داند)
        $videoSchemas = app(VideoSchemaService::class)->forHomepage($s, $members, $this->homeVideoUploadDate('en'));

        Media::preloadForRecords($latestArticles);

        return view('home', compact('latestArticles', 's', 'members', 'videoSchemas'));
    }

    // صفحه اصلی — ترکی
    public function homeTr()
    {
        $latestArticles = Article::published()
            ->locale('tr')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        [$s, $members] = $this->homeSettings('tr');

        $videoSchemas = app(VideoSchemaService::class)->forHomepage($s, $members, $this->homeVideoUploadDate('tr'));

        Media::preloadForRecords($latestArticles);

        return view('tr.home', compact('latestArticles', 's', 'members', 'videoSchemas'));
    }

    // خواندن یک‌جای تنظیمات صفحه اصلی از دیتابیس (یک کوئری)
    private function homeSettings(string $locale): array
    {
        $prefix = "home.$locale.";

        $raw = SiteSetting::byPrefix("home.$locale");

        $s = [];
        foreach ($raw as $key => $value) {
            $s[substr($key, strlen($prefix))] = $value;
        }

        $members = json_decode($s['members'] ?? '[]', true) ?: [];
        unset($s['members']);

        return [$s, $members];
    }

    // تاریخِ fallbackِ uploadDate برای ویدیوهای embedِ صفحه‌ی اصلی (که تاریخِ فایل ندارند): آخرین
    // باری که هر یک از تنظیماتِ صفحه‌ی اصلی ذخیره شده — یعنی «کِی این محتوا روی سایت منتشر شد».
    // اگر هیچ ردیفی نبود (نصبِ تازه، بدونِ ویدیو) به now برمی‌گردیم؛ چون بدونِ ویدیو اصلاً
    // VideoObjectی ساخته نمی‌شود، این حالت هیچ‌وقت روی خروجی اثر ندارد.
    private function homeVideoUploadDate(string $locale): string
    {
        $latest = SiteSetting::where('key', 'like', "home.$locale.%")->max('updated_at');

        return ($latest ? Carbon::parse($latest) : now())->toIso8601String();
    }

    // صفحه درباره ما — انگلیسی
    public function about()
    {
        [$about, $stats, $certificates, $gallery, $timeline] = $this->aboutSettings('en');

        return view('about', compact('about', 'stats', 'certificates', 'gallery', 'timeline'));
    }

    // صفحه درباره ما — ترکی
    public function aboutTr()
    {
        [$about, $stats, $certificates, $gallery, $timeline] = $this->aboutSettings('tr');

        return view('tr.about', compact('about', 'stats', 'certificates', 'gallery', 'timeline'));
    }

    // خواندن یک‌جای تنظیمات صفحه درباره ما از دیتابیس (یک کوئری)
    private function aboutSettings(string $locale): array
    {
        $prefix = "about.$locale.";

        $raw = SiteSetting::byPrefix("about.$locale");

        $about = [];
        foreach ($raw as $key => $value) {
            $about[substr($key, strlen($prefix))] = $value;
        }

        $stats = json_decode($about['stats'] ?? '[]', true) ?: [];
        $certificates = $this->sortBySortOrder(json_decode($about['certificates'] ?? '[]', true) ?: []);
        $gallery = $this->sortBySortOrder(json_decode($about['gallery'] ?? '[]', true) ?: []);
        $timeline = $this->sortBySortOrder(json_decode($about['timeline'] ?? '[]', true) ?: []);
        unset($about['stats'], $about['certificates'], $about['gallery'], $about['timeline']);

        // ابعاد/نوع تصویر هیرو و og:image — برای ImageObject در JSON-LD و متاتگ‌های og:image:*
        $heroMeta = $this->imageMeta($about['hero_image'] ?? null);
        $about['hero_image_width'] = $heroMeta['width'] ?? null;
        $about['hero_image_height'] = $heroMeta['height'] ?? null;

        $ogMeta = $this->imageMeta($about['seo_og_image'] ?? null);
        $about['seo_og_image_width'] = $ogMeta['width'] ?? null;
        $about['seo_og_image_height'] = $ogMeta['height'] ?? null;
        $about['seo_og_image_mime'] = $ogMeta['mime'] ?? null;

        return [$about, $stats, $certificates, $gallery, $timeline];
    }

    // ابعاد و نوع MIME یک فایل روی دیسک public — بدون شکستن صفحه اگر فایل موجود/خوانا نباشد
    private function imageMeta(?string $path): array
    {
        if (! $path) {
            return [];
        }

        $absolute = Storage::disk('public')->path($path);

        if (! is_file($absolute) || ! ($info = @getimagesize($absolute))) {
            return [];
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'] ?? null,
        ];
    }

    // مرتب‌سازی آیتم‌های ریپیتر بر اساس فیلد sort_order (خالی‌ها آخر قرار می‌گیرند)
    private function sortBySortOrder(array $items): array
    {
        usort($items, fn ($a, $b) => ($a['sort_order'] ?? 9999) <=> ($b['sort_order'] ?? 9999));

        return $items;
    }

    // لیست مقالات — انگلیسی
    public function index()
    {
        return $this->renderIndex('en', 'blog');
    }

    // لیست مقالات — ترکی
    public function indexTr()
    {
        return $this->renderIndex('tr', 'tr.blog');
    }

    // تک‌مقاله — انگلیسی
    public function show(string $slug)
    {
        return $this->renderShow('en', $slug, 'blog-post');
    }

    // تک‌مقاله — ترکی
    public function showTr(string $slug)
    {
        return $this->renderShow('tr', $slug, 'tr.blog-post');
    }

    private function renderIndex(string $locale, string $view)
    {
        // صفحه‌بندی — ۱۲تایی (گریدِ دوستونه، ۶ ردیف) تا با رشدِ آرشیو صفحه سنگین نشود؛
        // withQueryString پارامترهای احتمالیِ آینده را در لینک‌های صفحه‌ها حفظ می‌کند
        $articles = Article::published()
            ->locale($locale)
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $popular = Article::published()
            ->locale($locale)
            ->orderByDesc('views')
            ->take(3)
            ->get();

        // شمارش مقالات هر دسته برای سایدبار
        $categories = Article::published()
            ->locale($locale)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        Media::preloadForRecords($articles->concat($popular));

        return view($view, compact('articles', 'popular', 'categories'));
    }

    private function renderShow(string $locale, string $slug, string $view)
    {
        $article = Article::published()
            ->locale($locale)
            ->where('slug', $slug)
            ->firstOrFail();

        // افزایش شمارنده بازدید
        $article->increment('views');

        // مقالات مرتبط: هم‌دسته، غیر از خودش
        $related = Article::published()
            ->locale($locale)
            ->where('id', '!=', $article->id)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->orderByDesc('published_at')
            ->take(2)
            ->get();

        // آخرین مقالات برای سایدبار
        $latest = Article::published()
            ->locale($locale)
            ->where('id', '!=', $article->id)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        // نسخه‌ی زبان دیگر (اگر لینک شده باشد) — دوطرفه چک می‌کنیم
        $translation = $article->translation
            ?? $article->translations()->first();

        // عکس نویسنده در باکس پایان مقاله = همان عکس هیرو صفحه‌ی درباره ما
        // (یک منبع واحد برای پروفایل؛ اگر برای این زبان تنظیم نشده باشد، به نسخه‌ی انگلیسی برمی‌گردیم)
        $authorPhoto = SiteSetting::get("about.$locale.hero_image")
            ?? SiteSetting::get('about.en.hero_image');

        // متن‌های باکس نویسنده — از CMS (About Page Settings)؛ null یعنی «پیش‌فرضِ داخل قالب»
        $authorBox = self::authorBoxSettings($locale);

        // Video SEO — VideoObject برای ویدیوهای درون‌متنیِ مقاله (همان لینک‌هایی که در بدنه پخش‌کننده می‌شوند)
        $videoSchemas = app(VideoSchemaService::class)->forArticle($article);

        Media::preloadForRecords($related->concat($latest)->push($article));

        return view($view, compact('article', 'related', 'latest', 'translation', 'authorPhoto', 'authorBox', 'videoSchemas'));
    }

    // متن‌های باکسِ نویسنده‌ی پایانِ مقاله از CMS (یک کوئری) — مقدارِ خالی همان «تنظیم‌نشده» است
    // تا قالب به کپیِ پیش‌فرضِ فعلی برگردد (همان قراردادِ fallback-در-Blade بقیه‌ی صفحات).
    // static چون PreviewController هم برای رندرِ عینِ صفحه‌ی واقعی به همین داده نیاز دارد.
    public static function authorBoxSettings(string $locale): array
    {
        $raw = SiteSetting::byPrefix("about.$locale");
        $v = fn (string $key) => (($raw["about.$locale.author_box_$key"] ?? '') !== '') ? $raw["about.$locale.author_box_$key"] : null;

        return [
            'title' => $v('title'),
            'subtitle' => $v('subtitle'),
            'text' => $v('text'),
            'button_text' => $v('button_text'),
        ];
    }
}
