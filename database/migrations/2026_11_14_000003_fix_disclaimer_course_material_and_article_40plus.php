<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // دو اصلاحِ متنیِ جداگانه که هر دو در دیتابیسِ زنده نشسته‌اند و در هیچ کدی/seedِ قبلی
    // دست‌نخورده نمانده بودند:
    //
    // ۱) صفحه‌ی disclaimer (en/tr) — پاراگراف «Not Medical or Professional Advice» هنوز
    //    عبارتِ اصلیِ seed («course material»/«kurs materyalleri») را داشت؛ مهاجرتِ قبلی
    //    (2026_11_14_000001) فقط سه پاراگرافِ دیگرِ همین صفحه را پچ کرد، نه این یکی را.
    //
    // ۲) مقاله‌ی «self-defense-after-40» (en) — این مقاله هیچ seed/migration ی در این مخزن
    //    ندارد (محتوایش فقط در دیتابیسِ production است، ساخته‌شده از طریقِ پنل/AI Import)،
    //    پس تنها راه برای اصلاحِ آن از اینجا یک پچِ دیتاییِ guarded روی ستونِ body است —
    //    با جست‌وجوی مستقیمِ خودِ عبارت، نه با اتکا به slug (که اینجا قابلِ تأیید نیست).
    //    نسخه‌ی TR این مقاله عمداً در این مهاجرت دست‌نخورده می‌ماند: متنِ دقیقِ ترکیِ زنده‌اش
    //    در این مخزن/محیط در دسترس نیست و حدس‌زدنِ عبارتِ ترکیِ پزشکی/آموزشی ریسک‌ناک است —
    //    نیاز به تأییدِ متنِ دقیق از صاحبِ سایت دارد.
    public function up(): void
    {
        $this->patchDisclaimerCourseMaterial();
        $this->patchSelfDefenseAfter40Article();
    }

    public function down(): void
    {
        // no-op — برگرداندن یعنی بازگرداندنِ قاب‌بندیِ خدماتیِ حذف‌شده.
    }

    private function patchDisclaimerCourseMaterial(): void
    {
        $this->strReplaceRow('pages', 'disclaimer', 'en',
            'including articles, videos and course material',
            'including articles, videos and educational material'
        );

        $this->strReplaceRow('pages', 'disclaimer', 'tr',
            'makaleler, videolar ve kurs materyalleri dahil',
            'makaleler, videolar ve eğitim materyalleri dahil'
        );
    }

    private function strReplaceRow(string $table, string $slug, string $locale, string $old, string $new): void
    {
        $row = DB::table($table)->where('slug', $slug)->where('locale', $locale)->first();
        if (! $row || ! str_contains((string) $row->body, $old)) {
            return;
        }
        DB::table($table)->where('id', $row->id)->update([
            'body' => str_replace($old, $new, (string) $row->body),
            'updated_at' => now(),
        ]);
    }

    private function patchSelfDefenseAfter40Article(): void
    {
        $old = 'shape programs for students over 40';
        $new = 'inform how training can be adapted for people over 40';

        DB::table('articles')
            ->where('body', 'like', '%'.$old.'%')
            ->get(['id', 'body'])
            ->each(function ($row) use ($old, $new) {
                DB::table('articles')->where('id', $row->id)->update([
                    'body' => str_replace($old, $new, (string) $row->body),
                    'updated_at' => now(),
                ]);
            });
    }
};
