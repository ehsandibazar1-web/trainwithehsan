<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * فونتِ Manrope از 2026-07-21 خودمیزبان است (public/fonts، فونتِ متغیرِ وزن ۴۰۰ تا ۸۰۰) —
 * هیچ درخواستی به fonts.googleapis.com/fonts.gstatic.com نباید بماند، و latin-ext (حروفِ
 * ترکی ş/ğ/İ) باید در دسترس باشد.
 */
class SelfHostedFontTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_font_files_exist_in_public_fonts(): void
    {
        $this->assertFileExists(public_path('fonts/manrope-latin.woff2'));
        $this->assertFileExists(public_path('fonts/manrope-latin-ext.woff2'));
    }

    public function test_homepage_uses_the_self_hosted_font_and_no_google_fonts(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('fonts/manrope-latin.woff2', $html);
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
    }

    public function test_turkish_homepage_also_preloads_the_latin_ext_subset(): void
    {
        // صفحاتِ ترکی تقریبا در هر جمله حروفِ latin-ext دارند — preloadِ هر دو فایل حیاتی است
        $html = $this->get('/tr')->assertOk()->getContent();

        $this->assertStringContainsString('fonts/manrope-latin-ext.woff2', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }
}
