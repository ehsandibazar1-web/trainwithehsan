<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ادامه‌ی 2026_11_20_000001: آن مهاجرت حدس زد که about.{en,tr}.seo_title مقدارِ
    // قدیمیِ خودِ کدِ پیش از این نشست را دارد — اشتباه بود. با خواندنِ مستقیمِ HTMLِ زنده
    // (بعد از این‌که دامنه به‌طور موقت به allowlist این محیط اضافه شد) معلوم شد
    // about.en.seo_title/seo_description و about.tr.seo_title/seo_description هرکدام
    // یک متنِ کاملاً جداگانه و دستی داشتند — نه پیش‌فرضِ قدیمِ کد، نه پیش‌فرضِ جدید — که
    // هنوز «Coach»/«Antrenörü»/«Instructor»/«Eğitmeni» را به‌عنوانِ جمله‌بندیِ فعلی/ارائه‌ی
    // خدمت حمل می‌کرد. og:title/og:description هم از همین دو کلید ساخته می‌شوند
    // (about.blade.php: @section('og_title', $v('seo_title', ...)))، پس این یک پچ هم
    // هر دو تگ را درست می‌کند.
    //
    // گاردِ idempotent: فقط روی همان مقدارِ دقیقِ زنده‌ای که از صفحه خوانده شد.
    public function up(): void
    {
        $this->patchIfMatches(
            'about.en.seo_title',
            'About Ehsan Dibazar | Martial Arts Coach, Sports Scientist & Self-Defense Instructor',
            'About Ehsan Dibazar | Martial Artist, Coach & Sport Scientist'
        );

        $this->patchIfMatches(
            'about.en.seo_description',
            'Learn more about Ehsan Dibazar, a martial arts coach, sports scientist and self-defense instructor. Discover his professional journey, international certifications, coaching philosophy and dedication to helping people build confidence, strength and practical skills.',
            'Learn more about Ehsan Dibazar — a martial artist, coach and sport scientist, and the creator of the Martial Intelligence approach. Discover his background, martial-arts journey, credentials and the thinking behind his work.'
        );

        $this->patchIfMatches(
            'about.tr.seo_title',
            'Ehsan Dibazar Hakkında | Dövüş Sporları Antrenörü, Spor Bilimci ve Öz Savunma Eğitmeni',
            'Ehsan Dibazar Hakkında | Dövüş Sanatçısı, Koç ve Spor Bilimci'
        );

        $this->patchIfMatches(
            'about.tr.seo_description',
            "Dövüş sporları antrenörü, spor bilimci ve öz savunma eğitmeni Ehsan Dibazar'ı yakından tanıyın. Profesyonel yolculuğunu, uluslararası sertifikalarını, antrenörlük yaklaşımını ve insanların özgüven ile beceri kazanmalarına nasıl yardımcı olduğunu keşfedin.",
            "Dövüş sanatçısı, koç ve spor bilimci Ehsan Dibazar'ı yakından tanıyın — Martial Intelligence yaklaşımının yaratıcısı. Geçmişini, dövüş sanatları yolculuğunu, sertifikalarını ve çalışmalarının arkasındaki düşünceyi keşfedin."
        );
    }

    public function down(): void
    {
        // no-op — برگرداندن یعنی بازگرداندنِ قاب‌بندیِ «Coach/Instructor».
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
