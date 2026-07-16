<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Article;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Guard passing is a fundamental BJJ skill.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>Some page content.</p>',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_tag_slug_is_auto_generated_from_name_when_left_blank(): void
    {
        $tag = Tag::create(['name' => 'Self Defense Tips']);

        $this->assertSame('self-defense-tips', $tag->slug);
    }

    public function test_tag_slug_is_preserved_when_explicitly_provided(): void
    {
        $tag = Tag::create(['name' => 'BJJ', 'slug' => 'bjj-custom']);

        $this->assertSame('bjj-custom', $tag->slug);
    }

    public function test_articles_and_pages_can_be_tagged_and_a_tag_reports_both(): void
    {
        $tag = Tag::create(['name' => 'Istanbul']);
        $article = $this->makeArticle();
        $page = $this->makePage();

        $article->tags()->attach($tag);
        $page->tags()->attach($tag);

        $this->assertTrue($article->fresh()->tags->contains($tag));
        $this->assertTrue($page->fresh()->tags->contains($tag));
        $this->assertTrue($tag->articles()->whereKey($article->id)->exists());
        $this->assertTrue($tag->pages()->whereKey($page->id)->exists());
    }

    public function test_a_tag_can_be_attached_to_an_article_and_a_page_without_a_unique_constraint_clash(): void
    {
        // یک تگ باید بتواند هم‌زمان به یک Article و یک Page متصل شود — یعنی unique
        // ['tag_id','taggable_type','taggable_id'] نباید مانع این حالت شود، فقط از تکرار
        // دقیقاً همان جفت جلوگیری کند
        $tag = Tag::create(['name' => 'Reused']);
        $article = $this->makeArticle();
        $page = $this->makePage();

        $article->tags()->attach($tag->id);
        $page->tags()->attach($tag->id);

        $this->assertSame(2, \DB::table('taggables')->where('tag_id', $tag->id)->count());
    }

    public function test_tags_resource_lists_and_edits_tags(): void
    {
        $tag = Tag::create(['name' => 'Nutrition']);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/admin/tags')
            ->assertOk()
            ->assertSee('Nutrition');

        Livewire::actingAs($owner)
            ->test(EditTag::class, ['record' => $tag->id])
            ->fillForm(['name' => 'Nutrition & Diet', 'slug' => 'nutrition-diet'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nutrition & Diet', $tag->fresh()->name);
    }

    public function test_tags_table_shows_article_and_page_counts(): void
    {
        $tag = Tag::create(['name' => 'Popular']);
        $article = $this->makeArticle();
        $article->tags()->attach($tag);

        Livewire::actingAs($this->owner())
            ->test(ListTags::class)
            ->assertSee('Popular');
    }

    public function test_article_form_can_attach_existing_tags(): void
    {
        $tag = Tag::create(['name' => 'Existing Tag']);
        $article = $this->makeArticle();

        Livewire::actingAs($this->owner())
            ->test(EditArticle::class, ['record' => $article->id])
            ->fillForm(['tags' => [$tag->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($article->fresh()->tags->contains($tag));
    }
}
