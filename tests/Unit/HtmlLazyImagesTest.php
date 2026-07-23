<?php

namespace Tests\Unit;

use App\Support\Html;
use PHPUnit\Framework\TestCase;

class HtmlLazyImagesTest extends TestCase
{
    public function test_adds_lazy_and_async_to_plain_img(): void
    {
        $out = Html::lazyLoadImages('<p>x</p><img src="/a.jpg" alt="a"><p>y</p>');

        $this->assertStringContainsString('<img loading="lazy" decoding="async" src="/a.jpg" alt="a">', $out);
        // بقیه‌ی محتوا دست‌نخورده
        $this->assertStringContainsString('<p>x</p>', $out);
        $this->assertStringContainsString('<p>y</p>', $out);
    }

    public function test_does_not_double_add_when_loading_already_present(): void
    {
        $in = '<img loading="eager" src="/hero.jpg">';
        $this->assertSame($in, Html::lazyLoadImages($in));
    }

    public function test_handles_multiple_images(): void
    {
        $out = Html::lazyLoadImages('<img src="/1.jpg"><img src="/2.jpg">');
        $this->assertSame(2, substr_count($out, 'loading="lazy"'));
    }

    public function test_null_and_empty_are_safe(): void
    {
        $this->assertSame('', Html::lazyLoadImages(null));
        $this->assertSame('', Html::lazyLoadImages(''));
    }

    public function test_content_without_images_is_unchanged(): void
    {
        $in = '<p>No images here — just <a href="/x">a link</a>.</p>';
        $this->assertSame($in, Html::lazyLoadImages($in));
    }
}
