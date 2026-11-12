<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Media\MediaManager;
use App\Cms\Seo\SeoResolver;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
    }

    private function make(string $title, array $attrs = [], string $type = 'page'): Content
    {
        return Content::create($attrs + [
            'content_type_id' => ContentType::where('key', $type)->value('id'),
            'title' => $title, 'slug' => str($title)->slug(), 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);
    }

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_title_uses_template_and_custom_title(): void
    {
        $this->settings()->save('seo', ['site_name' => 'Firma', 'title_template' => '%title% | %site%']);
        $page = $this->make('O nas');

        $this->get('/o-nas')->assertOk()->assertSee('<title>O nas | Firma</title>', false);

        $page->update(['seo' => ['title' => 'Poznaj nas']]);
        $this->get('/o-nas')->assertSee('<title>Poznaj nas | Firma</title>', false);
    }

    public function test_home_title_uses_site_name_and_tagline(): void
    {
        $this->settings()->save('seo', ['site_name' => 'Firma', 'tagline' => 'Robimy dobrze']);
        $this->make('Home', ['slug' => 'home']);

        $this->get('/')->assertOk()->assertSee('<title>Firma – Robimy dobrze</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    }

    public function test_description_falls_back_to_excerpt_then_body_then_site(): void
    {
        $seo = app(SeoResolver::class);
        $this->settings()->save('seo', ['description' => 'Opis witryny']);

        $a = $this->make('A', ['seo' => ['description' => 'Własny opis']]);
        $b = $this->make('B', ['excerpt' => 'Zajawka wpisu']);
        $c = $this->make('C', ['html_cache' => '<h1>Tytuł</h1><p>'.str_repeat('słowo ', 60).'</p>']);
        $d = $this->make('D');

        $this->assertSame('Własny opis', $seo->description($a));
        $this->assertSame('Zajawka wpisu', $seo->description($b));
        $this->assertLessThanOrEqual(161, mb_strlen($seo->description($c)));
        $this->assertStringEndsWith('…', $seo->description($c));
        $this->assertSame('Opis witryny', $seo->description($d));
    }

    public function test_robots_meta_and_noindex_flags(): void
    {
        $this->make('Indeks');
        $this->make('Ukryta', ['seo' => ['noindex' => true, 'nofollow' => true]]);

        $this->get('/indeks')->assertSee('content="index, follow, max-image-preview:large"', false);
        $this->get('/ukryta')->assertSee('content="noindex, nofollow"', false);

        $this->settings()->save('seo', ['noindex_site' => true]);
        $this->get('/indeks')->assertSee('content="noindex, nofollow"', false);
    }

    public function test_custom_canonical_and_open_graph_with_image_and_alt(): void
    {
        $media = app(MediaManager::class)->store(UploadedFile::fake()->image('baner.jpg', 1600, 900), ['alt' => 'Baner firmy']);
        $this->make('Oferta', ['seo' => ['canonical' => 'https://example.com/oferta', 'og_title' => 'OG tytuł', 'og_image_id' => $media->id]]);

        $this->get('/oferta')->assertOk()
            ->assertSee('<link rel="canonical" href="https://example.com/oferta">', false)
            ->assertSee('property="og:title" content="OG tytuł"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('property="og:image:alt" content="Baner firmy"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);
    }

    public function test_json_ld_is_valid_and_cannot_break_out_of_script(): void
    {
        $post = $this->make('Wpis </script><script>alert(1)</script>', ['slug' => 'wpis'], 'post');
        $response = $this->get('/blog/wpis')->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('</script><script>alert', $html);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $data = json_decode($m[1], true);

        $this->assertSame('https://schema.org', $data['@context']);
        $types = array_column($data['@graph'], '@type');
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertSame($post->title, collect($data['@graph'])->firstWhere('@type', 'Article')['headline']);
    }

    public function test_preview_is_noindex_without_canonical(): void
    {
        $user = User::factory()->create();
        $draft = $this->make('Szkic', ['status' => 'draft']);

        $this->actingAs($user)->get("/cms/content/page/{$draft->id}/preview")
            ->assertOk()->assertSee('content="noindex, nofollow"', false)->assertDontSee('rel="canonical"', false);
    }

    public function test_sitemap_lists_only_indexable_published_content(): void
    {
        $this->make('Home', ['slug' => 'home']);
        $this->make('Widoczna');
        $this->make('Ukryta', ['seo' => ['noindex' => true]]);
        $this->make('Szkic', ['status' => 'draft']);
        $this->make('Przyszła', ['status' => 'scheduled', 'published_at' => now()->addDay()]);
        $this->make('Wpis', [], 'post');

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('/widoczna</loc>', $xml);
        $this->assertStringContainsString('/blog/wpis</loc>', $xml);
        $this->assertStringNotContainsString('/ukryta', $xml);
        $this->assertStringNotContainsString('/szkic', $xml);
        $this->assertStringNotContainsString('/przyszla', $xml);
        $this->assertStringNotContainsString('/home<', $xml);

        $this->settings()->save('seo', ['noindex_site' => true]);
        $this->assertStringNotContainsString('<url>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_robots_txt_points_to_sitemap_or_blocks_everything(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /cms')->assertSee('Sitemap: '.url('/sitemap.xml'));

        $this->settings()->save('seo', ['noindex_site' => true]);
        $this->get('/robots.txt')->assertSee('Disallow: /')->assertDontSee('Sitemap:');
    }

    public function test_content_form_saves_cleans_and_validates_seo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Strona', 'status' => 'published', 'fields' => [],
            'seo' => ['title' => ' Tytuł SEO ', 'description' => '', 'noindex' => true, 'nofollow' => false, 'focus_keyword' => 'kurs'],
        ])->assertRedirect();

        $this->assertSame(['title' => 'Tytuł SEO', 'focus_keyword' => 'kurs', 'noindex' => true], Content::firstWhere('title', 'Strona')->seo);

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Zła', 'status' => 'draft', 'fields' => [], 'seo' => ['canonical' => 'javascript:alert(1)', 'og_image_id' => 9999],
        ])->assertSessionHasErrors(['seo.canonical', 'seo.og_image_id']);

        $this->actingAs($user)->post('/cms/content/page', ['title' => 'Pusta', 'status' => 'draft', 'fields' => [], 'seo' => ['title' => '']])->assertRedirect();
        $this->assertNull(Content::firstWhere('title', 'Pusta')->seo);
    }

    public function test_seo_settings_page_saves_validates_and_keeps_unknown_keys_out(): void
    {
        $user = User::factory()->create();
        $payload = ['site_name' => 'Lumin', 'tagline' => 'CMS', 'description' => '', 'title_template' => '%title% / %site%', 'separator' => '-', 'twitter_handle' => '@lumin', 'noindex_site' => false, 'hack' => 'x'];

        $this->actingAs($user)->get('/cms/settings/seo')->assertOk();
        $this->actingAs($user)->put('/cms/settings/seo', $payload)->assertRedirect();

        $saved = $this->settings()->group('seo');
        $this->assertSame('Lumin', $saved['site_name']);
        $this->assertArrayNotHasKey('hack', $saved);

        $this->actingAs($user)->put('/cms/settings/seo', ['title_template' => 'brak', 'site_name' => ''] + $payload)
            ->assertSessionHasErrors(['title_template', 'site_name']);
    }

    public function test_settings_pages_require_login(): void
    {
        $this->get('/cms/settings/seo')->assertRedirect('/login');
    }
}
