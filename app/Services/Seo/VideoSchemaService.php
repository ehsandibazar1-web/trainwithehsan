<?php

namespace App\Services\Seo;

use App\Models\Media;

/**
 * می‌سازد: فهرستی از آرایه‌های VideoObject (schema.org) برای ویدیوهایی که همین حالا روی صفحه‌ی
 * اصلی نمایش داده می‌شوند — ردیفِ ویدیو (video1..3) و ویدیوهای نتایجِ اعضا. این «Video SEO» است:
 * فقط داده‌ی ساختاریِ نامرئی (JSON-LD) اضافه می‌شود تا موتورهای جست‌وجو ویدیوها را به‌عنوان
 * ویدیو بشناسند (rich result)؛ هیچ فیلدِ ادمینِ تازه، هیچ تغییری در ظاهر/رندرِ عمومی.
 *
 * فقط از داده‌ی موجود ساخته می‌شود (کپشن/تامبنیل/لینکِ embed/فایلِ آپلودشده/عکسِ عضو). هر ویدیویی
 * که منبع (فایل یا embedِ شناخته‌شده) یا thumbnailUrl نداشته باشد رد می‌شود، تا فقط VideoObjectهای
 * معتبر منتشر شوند (Google برای VideoObject به thumbnailUrl و یک منبع نیاز دارد).
 */
class VideoSchemaService
{
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
     * یک VideoObject از داده‌ی خام — یا null اگر منبع/تامبنیلِ معتبر نداشته باشد.
     *
     * @return array<string, mixed>|null
     */
    private function build(string $name, string $description, string $embedRaw, string $filePath, string $thumbPath): ?array
    {
        $embedUrl = $this->embedUrl($embedRaw);
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

    // لینکِ عادیِ ویدیو → embedUrlِ متعارف (فقط ارائه‌دهنده‌های شناخته‌شده) — همان تشخیصِ
    // home.blade.php، اینجا یک‌بار در سرویس تا رندر و schema هم‌داستان بمانند
    private function embedUrl(string $u): ?string
    {
        if ($id = $this->youTubeId($u)) {
            return 'https://www.youtube.com/embed/'.$id;
        }

        if ($id = $this->vimeoId($u)) {
            return 'https://player.vimeo.com/video/'.$id;
        }

        return null;
    }

    // تامبنیل: عکسِ آپلودشده اگر باشد، وگرنه تامبنیلِ ثابتِ یوتیوب از روی شناسه — Vimeo/سایر بدونِ
    // عکس، تامبنیلِ ثابت ندارند (نیاز به API) پس null → آن ویدیو بدونِ schema می‌ماند
    private function thumbnailUrl(string $thumbPath, string $embedRaw): ?string
    {
        if (filled($thumbPath)) {
            return asset('storage/'.ltrim($thumbPath, '/'));
        }

        if ($id = $this->youTubeId($embedRaw)) {
            return 'https://img.youtube.com/vi/'.$id.'/hqdefault.jpg';
        }

        return null;
    }

    private function uploadDate(string $filePath): ?string
    {
        return Media::where('disk_path', $filePath)->first()?->created_at?->toIso8601String();
    }

    private function youTubeId(string $u): ?string
    {
        $u = trim($u);
        if ($u === '') {
            return null;
        }

        return preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $u, $m)
            ? $m[1]
            : null;
    }

    private function vimeoId(string $u): ?string
    {
        $u = trim($u);
        if ($u === '') {
            return null;
        }

        return preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $u, $m) ? $m[1] : null;
    }
}
