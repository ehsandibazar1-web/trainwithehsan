<?php

namespace Tests\Feature;

use App\Filament\Pages\HomepageSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstaShowcaseSaveTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_row1_embed_url_actually_persists_through_the_form(): void
    {
        Livewire::actingAs($this->owner())
            ->test(HomepageSettings::class)
            ->set('data.en.insta_showcase_enabled', true)
            ->set('data.en.insta_embed_url', 'https://www.instagram.com/reel/ROW1/')
            ->set('data.en.insta_showcase2_enabled', true)
            ->set('data.en.insta_showcase2_embed_url', 'https://www.instagram.com/reel/ROW2/')
            ->set('data.en.insta_url', 'https://instagram.com/ehsandibazar')
            ->call('save');

        // The whole point of the bug fix: Row 1's embed URL now lands in the key
        // the public template actually reads (insta_embed_url), not a dead key.
        $this->assertSame('https://www.instagram.com/reel/ROW1/', SiteSetting::get('home.en.insta_embed_url'));
        $this->assertSame('https://www.instagram.com/reel/ROW2/', SiteSetting::get('home.en.insta_showcase2_embed_url'));
        $this->assertSame('https://instagram.com/ehsandibazar', SiteSetting::get('home.en.insta_url'));
    }

    // رفتار «آپلود عکس» و «چسباندن لینک» باید بین دو ردیف کاملاً یکسان باشد — نمایش امبد زنده فقط
    // به وجود embed_url بستگی دارد و نمایش fallback_image فقط به آپلود آن عکس، مستقل از توگل
    // enabled؛ این تست دقیقاً همان سناریوهایی را پوشش می‌دهد که کاربر واقعی گزارش کرد.
    public function test_row1_embed_shows_without_the_enabled_toggle_being_on(): void
    {
        SiteSetting::set('home.en.insta_embed_url', 'https://www.instagram.com/p/ROW1NOENABLE/', 'homepage');
        SiteSetting::set('home.en.insta_showcase_enabled', false, 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-insta-url="https://www.instagram.com/p/ROW1NOENABLE/"', false);
    }

    public function test_row2_embed_shows_without_the_enabled_toggle_being_on(): void
    {
        SiteSetting::set('home.en.insta_showcase2_embed_url', 'https://www.instagram.com/p/ROW2NOENABLE/', 'homepage');
        SiteSetting::set('home.en.insta_showcase2_enabled', false, 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-insta-url="https://www.instagram.com/p/ROW2NOENABLE/"', false);
    }

    public function test_row1_fallback_image_shows_without_the_enabled_toggle_being_on(): void
    {
        SiteSetting::set('home.en.insta_showcase_fallback_image', 'homepage/fallback1.jpg', 'homepage');
        SiteSetting::set('home.en.insta_showcase_enabled', false, 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('homepage/fallback1.jpg', false);
    }

    public function test_row2_fallback_image_shows_and_makes_the_row_visible_without_the_enabled_toggle_being_on(): void
    {
        SiteSetting::set('home.en.insta_showcase2_fallback_image', 'homepage/fallback2.jpg', 'homepage');
        SiteSetting::set('home.en.insta_showcase2_enabled', false, 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('<section class="insta-showcase insta-showcase--row2">', false)
            ->assertSee('homepage/fallback2.jpg', false);
    }

    public function test_row2_stays_hidden_when_nothing_is_configured(): void
    {
        // No enabled/embed_url/fallback_image set at all for row 2 — the untouched-install case.
        // (The CSS rule for .insta-showcase--row2 is always present in <style>, so we assert
        // against the actual DOM element, not the class name string.)
        $this->get('/')
            ->assertOk()
            ->assertDontSee('<section class="insta-showcase insta-showcase--row2">', false);
    }
}
