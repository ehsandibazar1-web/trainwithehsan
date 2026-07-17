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
}
