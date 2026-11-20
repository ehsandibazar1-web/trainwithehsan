<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // صاحبِ سایت، داخلِ پنل ادمین (About Page Settings)، مقدارِ صریحی برای
    // about.en.hero_title/about.en.hero_bio در site_settings ذخیره کرده بود — متنِ
    // قدیمیِ «...Instructor»/«Teaching martial arts...» که اولویتش از پیش‌فرضِ کد بالاتره
    // (همان "fallback در Blade تا وقتی ادمین ویرایش کند" — این‌جا دقیقاً ویرایش کرده بود،
    // پس اصلاحِ پیش‌فرضِ کد در about.blade.php هیچ اثری روی خروجیِ زنده نداشت). نسخه‌ی TR
    // همین دو فیلد در پنل خالی بود، برای همین همان پیش‌فرضِ جدیدِ کد را درست نشان می‌داد —
    // ناهماهنگیِ EN/TR که صاحبِ سایت با کپی‌کردنِ مستقیمِ متنِ پنل گزارش داد.
    //
    // گاردِ idempotent: فقط وقتی مقدارِ ذخیره‌شده دقیقاً همان متنِ قدیمی باشد عوض می‌شود —
    // اجرای دوباره no-op است و اگر ادمین خودش بعداً چیزِ دیگری تایپ کند دست‌نخورده می‌ماند.
    public function up(): void
    {
        $this->patchIfMatches(
            'about.en.hero_title',
            'Martial arts & self-defense instructor, MSc in Sport Science, and developer of the Martial Intelligence concept',
            'Martial artist, coach & sport scientist, MSc in Sport Science, and developer of the Martial Intelligence concept'
        );

        $this->patchIfMatches(
            'about.en.hero_bio',
            "Teaching martial arts and building people's self-defense capability has always been one of the most rewarding things I do. Helping people become stronger gives my life meaning. My biggest concern is raising the quality of training and teaching in martial arts and self-defense — especially for beginners — so they keep going with more enjoyment and consistency, and get real, useful results in their lives. I believe martial arts, trained the right way, genuinely make people's lives better.",
            'Martial arts and sport science have shaped my life. I care about raising the quality of training and teaching in martial arts and self-defense — especially for beginners — so people keep going with more enjoyment and consistency.'
        );

        // همان ریسک برای SEO title/description (که og_title/og_description هم از همین دو
        // کلید می‌خوانند — دو برابر این را مهم می‌کند) — با همان گارد، هم برای en هم tr،
        // چون نمی‌دانیم کدام لوکال واقعاً override شده بود.
        $this->patchIfMatches(
            'about.en.seo_title',
            'Ehsan Dibazar | Muay Thai & Self-Defense Instructor',
            'Ehsan Dibazar | Martial Artist, Coach & Sport Scientist'
        );
        $this->patchIfMatches(
            'about.tr.seo_title',
            'Ehsan Dibazar | Muay Thai ve Kendini Savunma Eğitmeni',
            'Ehsan Dibazar | Dövüş Sanatçısı, Koç ve Spor Bilimci'
        );
        // seo_description عمداً این‌جا پچ نمی‌شود: متنِ پیش‌فرضش شاملِ «{$yearsExperience}+»ی
        // محاسبه‌شده بود (now()->year - 2013) — اگر مقدارِ ذخیره‌شده در پنل زمانِ دیگری
        // auto-fill/تایپ شده باشد، عددش با حدسِ این مهاجرت یکی نیست و گارد match نمی‌کند؛
        // حدس‌زدنِ یک رشته‌ی طولانی‌تر با عددِ نامطمئن ریسکِ یک پچِ بی‌اثر دارد، نه خطرناک، ولی
        // بی‌فایده — پس بهتر است صاحبِ سایت خودش این فیلد را در پنل چک/ویرایش کند.
    }

    public function down(): void
    {
        // no-op — برگرداندن یعنی بازگرداندنِ قاب‌بندیِ «Instructor» که حذفش هدفِ اصلی بود.
    }

    private function patchIfMatches(string $key, string $expectedOld, string $new): void
    {
        $row = DB::table('site_settings')->where('key', $key)->first();
        if (! $row || (string) $row->value !== $expectedOld) {
            return;
        }
        DB::table('site_settings')->where('id', $row->id)->update([
            'value' => $new,
            'updated_at' => now(),
        ]);
    }
};
