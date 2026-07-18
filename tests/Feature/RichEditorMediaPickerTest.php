<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\RichContent\MediaLibraryRichContentPlugin;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دکمه‌ی Media Library درونِ RichEditor — حالا از پنجره‌ی انتخابِ رسانه‌ی یکپارچه (MediaPickerInput)
 * به‌جای Select جست‌وجوپذیر استفاده می‌کند. منطقِ قابل-تستِ هسته (insertContentFor/imageNode/
 * downloadLinkHtml)، سازگاری با sanitize، ردگیریِ استفاده‌ی تصویرِ درون‌متنی، و بی‌رگرسیون‌بودنِ فرمِ
 * مقاله سنجیده می‌شود. تعاملِ واقعیِ دکمه/مودال در مرورگر دستی تأیید می‌شود.
 */
class RichEditorMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_registers_a_media_library_tool_action_and_toolbar_button(): void
    {
        $plugin = MediaLibraryRichContentPlugin::make('articles/inline');

        $this->assertSame('mediaLibrary', $plugin->getEditorTools()[0]->getName());
        $this->assertSame('mediaLibrary', $plugin->getEditorActions()[0]->getName());
        $this->assertSame(['mediaLibrary'], $plugin->getEnabledToolbarButtons());
        // بدونِ افزونه‌ی TipTap سفارشی — پس هیچ JS build ای لازم نیست
        $this->assertSame([], $plugin->getTipTapJsExtensions());
    }

    public function test_insert_content_for_an_image_builds_an_image_node_with_alt(): void
    {
        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => 'http://localhost/storage/articles/hero.jpg', 'type' => 'image', 'alt_text' => 'Existing alt',
        ]);

        // ALTِ صریح برنده است
        $node = MediaLibraryRichContentPlugin::insertContentFor($media, 'A fighter');
        $this->assertIsArray($node);
        $this->assertSame('image', $node['type']);
        $this->assertSame($media->url, $node['attrs']['src']); // فایلِ اصلی، نه WebP
        $this->assertSame('A fighter', $node['attrs']['alt']);

        // ALTِ خالی → از خودِ رسانه پر می‌شود
        $node2 = MediaLibraryRichContentPlugin::insertContentFor($media, null);
        $this->assertSame('Existing alt', $node2['attrs']['alt']);
    }

    public function test_insert_content_for_a_document_builds_a_download_link(): void
    {
        $media = Media::create([
            'original_name' => 'guide.pdf', 'disk' => 'public', 'disk_path' => 'content-images/guide.pdf',
            'url' => 'http://localhost/storage/content-images/guide.pdf', 'type' => 'document', 'mime_type' => 'application/pdf',
        ]);

        $html = MediaLibraryRichContentPlugin::insertContentFor($media);
        $this->assertIsString($html);
        $this->assertStringContainsString('<a href="http://localhost/storage/content-images/guide.pdf"', $html);
        $this->assertStringContainsString('guide.pdf', $html);

        // لینکِ دانلود باید از sanitize (#73) عبور کند — همان تگِ <a href> که بدنه از قبل نگه می‌دارد
        $clean = Str::sanitizeHtml('<p>'.$html.'</p>');
        $this->assertStringContainsString('<a', $clean);
        $this->assertStringContainsString('/storage/content-images/guide.pdf', $clean);
    }

    public function test_a_video_media_is_inserted_as_a_standalone_embed_link(): void
    {
        // ویدئوی خودمیزبان به لینکِ تنهای پاراگراف تبدیل می‌شود تا EmbedRenderer پخش‌کننده بسازد
        $media = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'articles/inline/clip.mp4',
            'url' => 'http://localhost/storage/articles/inline/clip.mp4', 'type' => 'video',
        ]);

        $html = MediaLibraryRichContentPlugin::insertContentFor($media);

        $this->assertIsString($html);
        $this->assertStringContainsString('<p><a href="http://localhost/storage/articles/inline/clip.mp4"', $html);
        $this->assertStringEndsWith('</a></p>', $html);
    }

    public function test_embed_link_html_is_a_standalone_paragraph_link(): void
    {
        // لینکِ تنهای پاراگراف — همان چیزی که EmbedRenderer به facade تبدیل می‌کند
        $html = MediaLibraryRichContentPlugin::embedLinkHtml('https://www.youtube.com/watch?v=abcdefghijk');

        $this->assertStringStartsWith('<p><a href="https://www.youtube.com/watch?v=abcdefghijk"', $html);
        $this->assertStringEndsWith('</a></p>', $html);
    }

    public function test_inserted_image_keeps_the_media_usage_tracked(): void
    {
        $media = Media::create([
            'original_name' => 'inline.jpg', 'disk' => 'public', 'disk_path' => 'articles/inline/x.jpg',
            'url' => 'http://localhost/storage/articles/inline/x.jpg', 'type' => 'image',
        ]);

        $node = MediaLibraryRichContentPlugin::insertContentFor($media, 'x');

        // متنِ مقاله شاملِ همان src می‌شود — چون src خودِ disk_path را دربردارد، MediaUsageScanner
        // آن را «در حال استفاده» می‌بیند (نه یتیم)
        Article::create([
            'locale' => 'en', 'title' => 'Uses inline', 'slug' => 'uses-inline',
            'body' => '<p><img src="'.$node['attrs']['src'].'" alt="x"></p>',
            'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $this->assertTrue($media->isInUse());
        $this->assertFalse($media->isOrphan());
    }

    public function test_inserted_image_markup_survives_html_sanitization(): void
    {
        $node = MediaLibraryRichContentPlugin::imageNode('/storage/articles/inline/x.jpg', 'A caption');
        $this->assertSame('image', $node['type']);
        $this->assertSame('/storage/articles/inline/x.jpg', $node['attrs']['src']);

        // #73: بدنه با Str::sanitizeHtml رندر می‌شود — <img src alt> باید بماند
        $clean = Str::sanitizeHtml('<p><img src="/storage/articles/inline/x.jpg" alt="A caption"></p>');
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('/storage/articles/inline/x.jpg', $clean);
    }

    public function test_article_create_form_still_mounts_with_the_plugin_attached(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        // اگر پلاگین/ابزار/اکشن اشتباه سیم‌کشی شده بود، mount شدنِ فرم خطا می‌داد
        Livewire::actingAs($owner)->test(CreateArticle::class)->assertSuccessful();
    }
}
