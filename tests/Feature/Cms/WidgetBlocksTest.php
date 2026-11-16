<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Media\Media;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_map_uses_openstreetmap_and_rejects_bad_coordinates(): void
    {
        $out = $this->render('map', ['lat' => '51.1079', 'lon' => '17.0385', 'zoom' => '0.02']);
        $this->assertStringContainsString('openstreetmap.org/export/embed.html', $out);
        $this->assertStringContainsString('marker=51.1079,17.0385', $out);

        $this->assertStringNotContainsString('<iframe', $this->render('map', ['lat' => '999', 'lon' => '17']));
        $this->assertStringNotContainsString('<iframe', $this->render('map', ['lat' => 'x', 'lon' => '17']));
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
}
