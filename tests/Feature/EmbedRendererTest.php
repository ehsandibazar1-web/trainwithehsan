<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\Content\EmbedRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * embedهای درون‌متنی (فازِ ۱: یوتیوب/ویمئو/فایلِ خودمیزبان) — یک لینکِ تنهای پاراگراف به facadeِ
 * click-to-load تبدیل می‌شود. سرور هیچ <iframe> ای نمی‌فرستد (تا کلیک هیچ منبعِ ثالثی بار نمی‌شود)،
 * لینکِ درون‌خطی و بدنه‌ی بدونِ embed دست‌نخورده می‌ماند (backward-compatible).
 */
class EmbedRendererTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $html): string
    {
        return (new EmbedRenderer)->render($html);
    }

    public function test_a_standalone_youtube_link_becomes_a_click_to_load_facade(): void
    {
        $out = $this->render('<p><a href="https://www.youtube.com/watch?v=abcdefghijk">Watch</a></p>');

        $this->assertStringContainsString('class="twe-embed', $out);
        $this->assertStringContainsString('data-embed-kind="iframe"', $out);
        // privacy: nocookie، و مهم‌تر — هیچ <iframe> ای در HTMLِ سرور نیست (فقط در data-attribute)
        $this->assertStringContainsString('youtube-nocookie.com/embed/abcdefghijk', $out);
        $this->assertStringNotContainsString('<iframe', $out);
    }

    public function test_a_standalone_vimeo_link_becomes_a_facade(): void
    {
        $out = $this->render('<p><a href="https://vimeo.com/123456789">Clip</a></p>');

        $this->assertStringContainsString('twe-embed--vimeo', $out);
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $out);
        $this->assertStringNotContainsString('<iframe', $out);
    }

    public function test_a_standalone_instagram_link_becomes_an_instagram_facade(): void
    {
        $out = $this->render('<p><a href="https://www.instagram.com/reel/ABC123def/">post</a></p>');

        $this->assertStringContainsString('twe-embed--instagram', $out);
        $this->assertStringContainsString('data-embed-kind="instagram"', $out);
        $this->assertStringContainsString('instagram.com/reel/ABC123def/', $out);
        // blockquote/embed.js فقط هنگامِ کلیک ساخته می‌شود — در HTMLِ سرور نیست
        $this->assertStringNotContainsString('<blockquote', $out);
    }

    public function test_a_standalone_tiktok_link_becomes_a_tiktok_facade_with_video_id(): void
    {
        $out = $this->render('<p><a href="https://www.tiktok.com/@someone/video/1234567890123456789">clip</a></p>');

        $this->assertStringContainsString('twe-embed--tiktok', $out);
        $this->assertStringContainsString('data-embed-kind="tiktok"', $out);
        $this->assertStringContainsString('data-embed-id="1234567890123456789"', $out);
        $this->assertStringNotContainsString('<blockquote', $out);
    }

    public function test_a_self_hosted_video_link_becomes_a_video_facade(): void
    {
        $url = url('/storage/media/library/clip.mp4');
        $out = $this->render('<p><a href="'.$url.'">clip.mp4</a></p>');

        $this->assertStringContainsString('data-embed-kind="video"', $out);
        $this->assertStringContainsString($url, $out);
        $this->assertStringNotContainsString('<video', $out); // پخش‌کننده فقط هنگامِ کلیک ساخته می‌شود
    }

    public function test_a_self_hosted_audio_link_becomes_an_audio_facade(): void
    {
        $url = url('/storage/media/library/track.mp3');
        $out = $this->render('<p><a href="'.$url.'">track.mp3</a></p>');

        $this->assertStringContainsString('data-embed-kind="audio"', $out);
        $this->assertStringContainsString('twe-embed--audio', $out);
    }

    public function test_it_handles_the_numeric_entity_encoding_the_sanitizer_applies_to_urls(): void
    {
        // Str::sanitizeHtml کاراکترِ «=» را به &#61; کد می‌کند — رندرر باید همان‌طور که واقعاً
        // از sanitizer بیرون می‌آید تشخیص دهد، نه فقط شکلِ خام را
        $out = $this->render('<p><a href="https://www.youtube.com/watch?v&#61;abcdefghijk">Watch</a></p>');

        $this->assertStringContainsString('twe-embed--youtube', $out);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abcdefghijk', $out);
    }

    public function test_an_inline_link_is_left_untouched(): void
    {
        // لینک درونِ یک جمله (نه تنها محتوای پاراگراف) → لینکِ عادی می‌ماند
        $html = '<p>See <a href="https://www.youtube.com/watch?v=abcdefghijk">this video</a> for more.</p>';

        $this->assertSame($html, $this->render($html));
    }

    public function test_a_non_provider_link_is_left_untouched(): void
    {
        $html = '<p><a href="https://example.com/article">Read more</a></p>';

        $this->assertSame($html, $this->render($html));
    }

    public function test_an_external_media_link_is_not_embedded(): void
    {
        // فایلِ رسانه روی دامنه‌ی دیگر — فقط منابعِ هم‌ریشه embed می‌شوند
        $html = '<p><a href="https://cdn.example.com/video/clip.mp4">external</a></p>';

        $this->assertSame($html, $this->render($html));
    }

    public function test_a_body_with_no_embed_hints_is_returned_byte_identical(): void
    {
        $html = '<h2>Heading</h2><p>Just some <strong>text</strong> and an <a href="/blog/other">internal link</a>.</p>';

        $this->assertSame($html, $this->render($html));
    }

    public function test_a_paragraph_style_attribute_does_not_block_detection(): void
    {
        // TipTap پاراگراف‌ها را با style="text-align:..." می‌سازد — نباید تشخیص را بشکند
        $out = $this->render('<p style="text-align: center"><a href="https://youtu.be/abcdefghijk" rel="noopener">v</a></p>');

        $this->assertStringContainsString('twe-embed--youtube', $out);
    }

    public function test_rendered_blog_post_shows_the_facade_and_no_live_iframe(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'With a video', 'slug' => 'with-a-video',
            'body' => '<p>Intro.</p><p><a href="https://www.youtube.com/watch?v=abcdefghijk">Watch</a></p>',
            'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $res = $this->get('/blog/'.$article->slug);
        $res->assertOk()
            ->assertSee('twe-embed', false)
            ->assertSee('data-embed-src', false)
            // هیچ iframe/اسکریپتِ یوتیوبی پیش از کلیک در HTML نیست
            ->assertDontSee('<iframe', false);
    }
}
