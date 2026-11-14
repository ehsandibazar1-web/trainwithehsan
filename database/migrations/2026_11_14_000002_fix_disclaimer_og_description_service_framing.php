<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // رفعِ یک جاافتادگیِ واقعی در مهاجرتِ قبلی (2026_11_14_000001): آن مهاجرت فقط
    // meta_description صفحه‌ی disclaimer را پچ کرد، نه og_description را — درحالی‌که
    // og_description همان مقداری‌ست که هم تگِ og:description و هم (از طریقِ fallback در
    // partials/video-og-tags.blade.php) تگِ twitter:description از آن ساخته می‌شوند. نتیجه:
    // بعد از دیپلوی، تگ‌های اجتماعیِ صفحه‌ی disclaimer هنوز می‌گفتند «... in our training
    // services» / «Antrenman hizmetlerimizdeki ...» — دقیقاً همان قاب‌بندیِ خدماتی/محلی که
    // این خانواده از مهاجرت‌ها قرار بود حذف کند. کشف‌شده با بررسیِ مستقیمِ مرورگر روی سایتِ
    // زنده بعد از دیپلویِ مهاجرتِ قبلی.
    //
    // همان گاردِ idempotent: هر ردیف فقط وقتی پچ می‌شود که og_description هنوز دقیقاً همان
    // مقدارِ seed باشد — اگر ادمین خودش از پنل عوضش کرده باشد، دست‌نخورده می‌ماند.
    public function up(): void
    {
        $this->patchOgDescription(
            'disclaimer',
            'en',
            'Important information about physical risk and the limits of guarantees in our training services.',
            'Important information about physical risk and the limits of any guarantees for the content on trainwithehsan.com.'
        );

        $this->patchOgDescription(
            'disclaimer',
            'tr',
            'Antrenman hizmetlerimizdeki fiziksel risk ve garanti sınırları hakkında önemli bilgiler.',
            'trainwithehsan.com\'daki içerikle ilgili fiziksel risk ve garanti sınırları hakkında önemli bilgiler.'
        );
    }

    public function down(): void
    {
        // no-op — برگرداندن یعنی بازگرداندنِ قاب‌بندیِ خدماتیِ حذف‌شده.
    }

    private function patchOgDescription(string $slug, string $locale, string $expectedOld, string $new): void
    {
        $row = DB::table('pages')->where('slug', $slug)->where('locale', $locale)->first();
        if (! $row) {
            return;
        }
        if ((string) $row->og_description !== $expectedOld) {
            return;
        }
        DB::table('pages')->where('id', $row->id)->update([
            'og_description' => $new,
            'updated_at' => now(),
        ]);
    }
};
