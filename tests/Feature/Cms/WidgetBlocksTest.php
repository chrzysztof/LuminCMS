<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Media\Media;
use App\Cms\Themes\Listing;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class WidgetBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function render(string $name, array $attrs): string
    {
        return app(BlockRegistry::class)->render($name, $attrs);
    }

    public function test_theme_ships_all_widgets_with_valid_fields(): void
    {
        $blocks = app(BlockRegistry::class)->all();

        foreach (['video', 'tabs', 'counters', 'gallery', 'logos', 'map'] as $name) {
            $this->assertArrayHasKey($name, $blocks);
            $this->assertSame(__('Widgety'), $blocks[$name]['category']);
            $this->assertNotEmpty($blocks[$name]['fields']);
        }
    }

    public function test_video_embeds_only_known_providers(): void
    {
        $yt = $this->render('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Film']);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $yt);
        $this->assertStringContainsString('loading="lazy"', $yt);

        $this->assertStringContainsString('player.vimeo.com/video/123456789?dnt=1', $this->render('video', ['url' => 'https://vimeo.com/123456789']));
        $this->assertStringContainsString('<video', $this->render('video', ['url' => 'https://example.com/film.mp4']));

        foreach (['javascript:alert(1)', 'https://evil.example/embed.html', 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', 'data:text/html,x'] as $bad) {
            $this->assertStringNotContainsString('<iframe', $this->render('video', ['url' => $bad]));
            $this->assertStringNotContainsString('<video', $this->render('video', ['url' => $bad]));
        }
    }

    public function test_tabs_render_accessible_markup_and_escape_content(): void
    {
        $out = $this->render('tabs', ['items' => [['title' => 'Jeden', 'text' => "a\n<script>x</script>"], ['title' => 'Dwa', 'text' => 'b']]]);

        $this->assertSame(2, substr_count($out, 'role="tab"'));
        $this->assertSame(2, substr_count($out, 'role="tabpanel"'));
        $this->assertStringContainsString('hidden', $out);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertSame('', trim($this->render('tabs', [])));
    }

    public function test_counters_show_final_numbers_without_javascript(): void
    {
        $out = $this->render('counters', ['items' => [['value' => 250, 'suffix' => '+', 'label' => 'Klientów']]]);

        $this->assertStringContainsString('data-lm-count="250"', $out);
        $this->assertStringContainsString('>250</span>+', $out);
    }

    public function test_map_uses_openstreetmap_and_rejects_bad_points(): void
    {
        $out = $this->render('map', ['location' => ['lat' => 51.1079, 'lng' => 17.0385, 'zoom' => 15, 'address' => 'Rynek, Wrocław']]);
        $this->assertStringContainsString('openstreetmap.org/export/embed.html', $out);
        $this->assertStringContainsString('marker=51.1079,17.0385', $out);
        $this->assertStringContainsString('mlat=51.1079', $out);

        $this->assertStringNotContainsString('<iframe', $this->render('map', ['location' => ['lat' => 999, 'lng' => 17]]));
        $this->assertStringNotContainsString('<iframe', $this->render('map', ['location' => ['lat' => 'x', 'lng' => 17]]));
        $this->assertStringNotContainsString('<iframe', $this->render('map', []));
    }

    public function test_gallery_and_logos_render_images(): void
    {
        $ids = collect([1, 2])->map(fn ($n) => Media::create([
            'disk' => 'public', 'path' => "x/{$n}.jpg", 'filename' => "{$n}.jpg", 'mime' => 'image/jpeg', 'size' => 1,
            'width' => 400, 'height' => 300, 'alt' => "Alt {$n}",
        ])->id)->all();

        $gallery = $this->render('gallery', ['images' => $ids, 'columns' => '4']);

        $this->assertStringContainsString('lm-gallery--4', $gallery);
        $this->assertSame(2, substr_count($gallery, 'loading="lazy"'));
        $this->assertStringContainsString('alt="Alt 1"', $gallery);
        $this->assertStringContainsString('data-lm-gallery', $gallery);
        $this->assertStringContainsString('lm-gallery--3', $this->render('gallery', ['images' => $ids, 'columns' => 'zly']));

        $this->assertStringContainsString('lm-logos__list', $this->render('logos', ['logos' => $ids, 'title' => 'Klienci']));
        $this->assertSame('', trim($this->render('logos', ['logos' => []])));
    }

    public function test_block_scripts_are_bundled_and_served(): void
    {
        $js = app(BlockRegistry::class)->js();
        $this->assertStringContainsString('data-lm-tabs', $js);
        $this->assertStringContainsString('data-lm-gallery', $js);
        $this->assertStringContainsString('data-lm-count', $js);

        $this->get('/theme-blocks.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
        $this->assertStringContainsString('.lm-lightbox', app(BlockRegistry::class)->css());
    }

    public function test_page_header_shows_title_excerpt_breadcrumbs_and_featured_image(): void
    {
        $type = ContentType::where('key', 'page')->first();
        $media = Media::create(['disk' => 'public', 'path' => 'x/h.jpg', 'filename' => 'h.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'width' => 1600, 'height' => 600, 'alt' => '']);
        $parent = Content::create(['content_type_id' => $type->id, 'title' => 'Oferta', 'slug' => 'oferta', 'status' => 'published', 'published_at' => now()]);
        $page = Content::create(['content_type_id' => $type->id, 'parent_id' => $parent->id, 'title' => 'Korepetycje <b>', 'slug' => 'korepetycje', 'excerpt' => 'Krótki opis', 'featured_media_id' => $media->id, 'status' => 'published', 'published_at' => now()]);

        $out = app(BlockRegistry::class)->render('page-header', ['breadcrumbs' => 1, 'background' => 'featured', 'align' => 'center', 'size' => 'large'], $page);

        $this->assertStringContainsString('<h1>Korepetycje &lt;b&gt;</h1>', $out);
        $this->assertStringContainsString('Krótki opis', $out);
        $this->assertStringContainsString('lm-page-header--center', $out);
        $this->assertStringContainsString('lm-page-header--image', $out);
        $this->assertStringContainsString('lm-page-header__bg', $out);
        $this->assertStringContainsString('>Oferta</a>', $out);
        $this->assertStringContainsString('aria-current="page"', $out);
        $this->assertStringNotContainsString('style=', $out);

        // Własny tytuł i bez ścieżki, bez obrazu.
        $plain = app(BlockRegistry::class)->render('page-header', ['title' => 'Mój tytuł', 'breadcrumbs' => 0], $page);
        $this->assertStringContainsString('<h1>Mój tytuł</h1>', $plain);
        $this->assertStringNotContainsString('lm-page-header__crumbs', $plain);
        $this->assertStringNotContainsString('lm-page-header--image', $plain);

        // W edytorze (bez bieżącej treści) pokazuje tytuł zastępczy.
        $this->assertStringContainsString('<h1>', app(BlockRegistry::class)->render('page-header', []));
    }

    public function test_page_header_uses_archive_heading_and_description_in_archive_templates(): void
    {
        app(Listing::class)->set('Kategoria: Matura', 'Wszystko o maturze.', new LengthAwarePaginator([], 0, 10));

        $out = app(BlockRegistry::class)->render('page-header', ['breadcrumbs' => 1]);

        $this->assertStringContainsString('<h1>Kategoria: Matura</h1>', $out);
        $this->assertStringContainsString('Wszystko o maturze.', $out);
        $this->assertStringContainsString('aria-current="page"', $out);
    }
}
