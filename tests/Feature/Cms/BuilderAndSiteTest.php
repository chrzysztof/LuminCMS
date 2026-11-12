<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuilderAndSiteTest extends TestCase
{
    use RefreshDatabase;

    private array $project = ['pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
        ['tagName' => 'h1', 'type' => 'text', 'components' => [['type' => 'textnode', 'content' => 'Witaj']]],
    ]]]]]]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function make(string $type, string $slug, string $status = 'published', ?Content $parent = null): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', $type)->value('id'), 'title' => ucfirst($slug), 'slug' => $slug,
            'status' => $status, 'parent_id' => $parent?->id, 'published_at' => $status === 'published' ? now() : null,
            'html_cache' => '<section class="lm-section"><h1>Witaj</h1></section>',
        ]);
    }

    public function test_builder_page_requires_login_and_builder_support(): void
    {
        $page = $this->make('page', 'home');
        $this->get("/cms/content/page/{$page->id}/builder")->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get("/cms/content/page/{$page->id}/builder")
            ->assertInertia(fn (Assert $p) => $p->component('content/builder')->where('content.id', $page->id)->where('project', null));

        ContentType::where('key', 'page')->update(['supports' => ['title']]);
        $this->actingAs(User::factory()->create())->get("/cms/content/page/{$page->id}/builder")->assertNotFound();
    }

    public function test_save_stores_project_and_renders_html_cache(): void
    {
        $page = $this->make('page', 'oferta', 'draft');
        $page->update(['html_cache' => null]);

        $this->actingAs(User::factory()->create())->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project])
            ->assertOk()->assertJsonPath('status', 'draft');

        $page->refresh();
        $this->assertSame('<h1>Witaj</h1>', $page->html_cache);
        $this->assertSame($this->project, $page->builder_data);
    }

    public function test_save_can_publish(): void
    {
        $page = $this->make('page', 'oferta', 'draft');

        $this->actingAs(User::factory()->create())->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project, 'status' => 'published'])->assertOk();

        $this->assertSame('published', $page->refresh()->status);
        $this->assertNotNull($page->published_at);
    }

    public function test_save_validates_input(): void
    {
        $page = $this->make('page', 'oferta');

        $this->actingAs(User::factory()->create())->putJson("/cms/content/page/{$page->id}/builder", [])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project, 'status' => 'scheduled'])->assertUnprocessable();
    }

    public function test_public_page_is_served_by_slug_and_hierarchy(): void
    {
        $parent = $this->make('page', 'oferta');
        $this->make('page', 'korepetycje', 'published', $parent);

        $this->get('/oferta')->assertOk()->assertSee('<h1>Witaj</h1>', false)->assertSee('lumin-blocks.css');
        $this->get('/oferta/korepetycje')->assertOk();
        $this->get('/korepetycje')->assertNotFound();
        $this->get('/inna/korepetycje')->assertNotFound();
    }

    public function test_posts_use_their_url_prefix(): void
    {
        $this->make('post', 'pierwszy');

        $this->get('/blog/pierwszy')->assertOk();
        $this->get('/pierwszy')->assertNotFound();
    }

    public function test_drafts_are_hidden_but_scheduled_become_visible_after_their_date(): void
    {
        $this->make('page', 'szkic', 'draft');
        $this->get('/szkic')->assertNotFound();

        $due = $this->make('page', 'plan', 'scheduled');
        $due->update(['published_at' => now()->subMinute()]);
        $this->get('/plan')->assertOk();

        $future = $this->make('page', 'przyszla', 'scheduled');
        $future->update(['published_at' => now()->addDay()]);
        $this->get('/przyszla')->assertNotFound();
    }

    public function test_preview_shows_drafts_to_logged_in_users_with_noindex(): void
    {
        $draft = $this->make('page', 'szkic', 'draft');

        $this->get("/cms/content/page/{$draft->id}/preview")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("/cms/content/page/{$draft->id}/preview")
            ->assertOk()->assertSee('noindex', false)->assertSee('<h1>Witaj</h1>', false);
    }

    public function test_home_serves_home_page_or_welcome(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Witaj</h1>', false);

        $this->make('page', 'home');
        $this->get('/')->assertOk()->assertSee('<h1>Witaj</h1>', false);
    }

    public function test_html_output_is_escaped_in_title(): void
    {
        $page = $this->make('page', 'xss');
        $page->update(['title' => '<script>alert(1)</script>']);

        $this->get('/xss')->assertDontSee('<script>alert(1)</script>', false);
    }
}
