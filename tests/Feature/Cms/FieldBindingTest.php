<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\BuilderRenderer;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Media\Media;
use App\Cms\Seo\SeoResolver;
use App\Cms\Shortcodes\Shortcodes;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldBindingTest extends TestCase
{
    use RefreshDatabase;

    private Content $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);

        FieldGroup::create([
            'key' => 'group_dane', 'title' => 'Dane',
            'fields' => [
                ['type' => 'text', 'name' => 'podtytul', 'label' => 'Podtytuł'],
                ['type' => 'url', 'name' => 'www', 'label' => 'Strona'],
                ['type' => 'image', 'name' => 'foto', 'label' => 'Zdjęcie', 'return_format' => 'array'],
                ['type' => 'wysiwyg', 'name' => 'opis', 'label' => 'Opis'],
                ['type' => 'true_false', 'name' => 'promocja', 'label' => 'Promocja'],
                ['type' => 'repeater', 'name' => 'lista', 'label' => 'Lista', 'sub_fields' => [['type' => 'text', 'name' => 'x']]],
            ],
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
        ]);

        $media = Media::create(['disk' => 'public', 'path' => 'x/f.jpg', 'filename' => 'f.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'width' => 400, 'height' => 300, 'alt' => 'Foto']);

        $this->page = Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Oferta', 'slug' => 'oferta', 'status' => 'published',
            'published_at' => now()->subMinute(),
            'fields' => ['podtytul' => 'Wartość <b>z pola</b>', 'www' => 'https://example.com/a', 'foto' => $media->id, 'opis' => '<p>Opis HTML</p>', 'promocja' => true],
        ]);
    }

    private function sc(string $text): string
    {
        return app(Shortcodes::class)->render($text, $this->page);
    }

    public function test_field_shortcode_formats(): void
    {
        $this->assertSame('Wartość &lt;b&gt;z pola&lt;/b&gt;', $this->sc('[field name="podtytul"]'));
        $this->assertSame(__('Tak'), $this->sc('[field name="promocja"]'));
        $this->assertSame('https://example.com/a', $this->sc('[field name="www" format="url"]'));
        $this->assertStringContainsString('<img', $this->sc('[field name="foto" format="image" class="lm-image"]'));
        $this->assertStringContainsString('class="lm-image"', $this->sc('[field name="foto" format="image" class="lm-image"]'));

        $html = $this->sc('[field name="opis" format="html"]');
        $this->assertStringContainsString('Opis', $html);
        $this->assertSame('Opis HTML', $this->sc('[field name="opis"]'));
    }

    public function test_built_in_fields_bind_featured_image_title_and_excerpt(): void
    {
        $media = Media::first();
        $this->page->update(['featured_media_id' => $media->id, 'excerpt' => 'Krótka zajawka']);

        $this->assertStringContainsString('<img', $this->sc('[field name="_featured" format="image" class="lm-image" /]'));
        $this->assertStringContainsString('alt="Foto"', $this->sc('[field name="_featured" format="image" /]'));
        $this->assertSame('Oferta', $this->sc('[field name="_title"]'));
        $this->assertSame('Krótka zajawka', $this->sc('[field name="_excerpt"]'));

        $this->page->update(['featured_media_id' => null]);
        $this->assertSame('', $this->sc('[field name="_featured" format="image" /]'));

        $project = ['pages' => [['frames' => [['component' => ['components' => [
            ['tagName' => 'img', 'type' => 'lm-image', 'classes' => ['lm-image'], 'attributes' => ['data-lm-field' => '_featured', 'data-lm-bind' => 'src']],
        ]]]]]]];
        $this->assertStringContainsString('[field name="_featured" format="image"', app(BuilderRenderer::class)->render($project));
    }

    public function test_empty_fields_use_the_fallback_and_unsafe_urls_are_dropped(): void
    {
        $this->assertSame('Domyślny', $this->sc('[field name="brak"]Domyślny[/field]'));
        $this->assertSame('#', $this->sc("[field name='brak' format='url' default='#']"));

        $this->page->update(['fields' => ['www' => 'javascript:alert(1)']]);
        $this->assertSame('#', $this->sc("[field name='www' format='url' default='#']"));
    }

    public function test_renderer_binds_elements_to_fields_with_clean_output(): void
    {
        $project = ['pages' => [['frames' => [['component' => ['components' => [
            ['tagName' => 'h2', 'type' => 'lm-heading', 'attributes' => ['data-lm-field' => 'podtytul'], 'components' => [['type' => 'textnode', 'content' => 'Zastępczy']]],
            ['tagName' => 'a', 'type' => 'link', 'classes' => ['lm-btn'], 'attributes' => ['href' => '/stary', 'data-lm-field' => 'www', 'data-lm-bind' => 'href'], 'components' => [['type' => 'textnode', 'content' => 'Odwiedź']]],
            ['tagName' => 'img', 'type' => 'lm-image', 'classes' => ['lm-image'], 'attributes' => ['data-lm-field' => 'foto', 'data-lm-bind' => 'src', 'alt' => 'x']],
            ['tagName' => 'div', 'attributes' => ['data-lm-field' => 'opis', 'data-lm-bind' => 'html']],
            ['tagName' => 'p', 'attributes' => ['data-lm-field' => 'zle nazwa"]', 'class' => 'x'], 'components' => [['type' => 'textnode', 'content' => 'Zwykły']]],
        ]]]]]]];

        $stored = app(BuilderRenderer::class)->render($project);
        $this->assertStringNotContainsString('data-lm-field', $stored);
        $this->assertStringContainsString('[field name="podtytul" format="text"]Zastępczy[/field]', $stored);
        $this->assertStringContainsString('<p class="x">Zwykły</p>', $stored);

        $out = $this->sc($stored);
        $this->assertStringContainsString('<h2>Wartość &lt;b&gt;z pola&lt;/b&gt;</h2>', $out);
        $this->assertStringContainsString('<a class="lm-btn" href="https://example.com/a">Odwiedź</a>', $out);
        $this->assertStringContainsString('<img', $out);
        $this->assertStringContainsString('<div>', $out);
        $this->assertStringNotContainsString('[field', $out);
    }

    public function test_builder_lists_fields_available_for_the_content(): void
    {
        $this->actingAs(User::factory()->create())->get("/cms/content/page/{$this->page->id}/builder")
            ->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('content/builder')
            ->has('customFields', 8) // 3 wbudowane + 5 pól własnych (bez repeatera)
            ->where('customFields.0.name', '_featured')
            ->where('customFields.1.preview', 'Oferta')
            ->where('customFields.3.name', 'podtytul')
            ->where('customFields.3.preview', 'Wartość z pola'));
    }

    public function test_seo_description_uses_field_values_not_raw_shortcodes(): void
    {
        $this->page->update(['html_cache' => '<h2>[field name="podtytul" format="text"]Zastępczy[/field]</h2>']);

        $this->get('/oferta')->assertOk()
            ->assertSee('Wartość', false)
            ->assertDontSee('[field', false);
    }

    private function cond(string $when, array $extra = [], string $text = 'X', array $children = []): array
    {
        return ['tagName' => 'p', 'attributes' => ['data-lm-show' => $when, ...$extra], 'components' => $children ?: [['type' => 'textnode', 'content' => $text]]];
    }

    private function renderProject(array $components): string
    {
        $stored = app(BuilderRenderer::class)->render(['pages' => [['frames' => [['component' => ['components' => $components]]]]]]);
        $this->assertStringNotContainsString('data-lm-show', $stored);

        return $this->sc($stored);
    }

    public function test_display_conditions_by_login_state(): void
    {
        $components = [$this->cond('logged_in', [], 'Dla zalogowanych'), $this->cond('logged_out', [], 'Dla gości')];

        $guest = $this->renderProject($components);
        $this->assertStringContainsString('<p>Dla gości</p>', $guest);
        $this->assertStringNotContainsString('Dla zalogowanych', $guest);

        $this->actingAs(User::factory()->create());
        $member = $this->renderProject($components);
        $this->assertStringContainsString('<p>Dla zalogowanych</p>', $member);
        $this->assertStringNotContainsString('Dla gości', $member);
    }

    public function test_display_conditions_by_field_and_period(): void
    {
        $out = $this->renderProject([
            $this->cond('filled', ['data-lm-show-field' => 'podtytul'], 'Jest podtytuł'),
            $this->cond('empty', ['data-lm-show-field' => 'podtytul'], 'Brak podtytułu'),
            $this->cond('filled', ['data-lm-show-field' => 'brak'], 'Brak pola - ukryte'),
            $this->cond('empty', ['data-lm-show-field' => 'brak'], 'Puste pole - widoczne'),
            $this->cond('filled', ['data-lm-show-field' => '_featured'], 'Obraz - ukryty'),
            $this->cond('period', ['data-lm-show-from' => now()->subDay()->toDateString(), 'data-lm-show-to' => now()->addDay()->toDateString()], 'W okresie'),
            $this->cond('period', ['data-lm-show-from' => now()->addDays(2)->toDateString()], 'Jeszcze nie'),
            $this->cond('period', ['data-lm-show-to' => now()->subDays(2)->toDateString()], 'Już po'),
            $this->cond('period', [], 'Bez dat - ukryte'),
        ]);

        foreach (['Jest podtytuł', 'Puste pole - widoczne', 'W okresie'] as $shown) {
            $this->assertStringContainsString($shown, $out);
        }
        foreach (['Brak podtytułu', 'Brak pola - ukryte', 'Obraz - ukryty', 'Jeszcze nie', 'Już po', 'Bez dat'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $out);
        }
        $this->assertStringNotContainsString('[show', $out);
    }

    public function test_nested_conditions_and_invalid_ones_fail_closed(): void
    {
        $inner = $this->cond('logged_in', [], 'Wewnętrzny');
        $outer = ['tagName' => 'div', 'attributes' => ['data-lm-show' => 'logged_out'], 'components' => [$inner, ['type' => 'textnode', 'content' => 'Zewnętrzny']]];

        $guest = $this->renderProject([$outer]);
        $this->assertStringContainsString('Zewnętrzny', $guest);
        $this->assertStringNotContainsString('Wewnętrzny', $guest);

        // Nieznany warunek jest ignorowany przez renderer (element zwykły), błędna nazwa pola ukrywa element.
        $this->assertStringContainsString('Zwykły', $this->renderProject([$this->cond('nieznany', [], 'Zwykły')]));
        $this->assertStringNotContainsString('Zly', $this->renderProject([$this->cond('filled', ['data-lm-show-field' => 'zła"]'], 'Zly')]));
        $this->assertSame('', trim($this->sc('[show when="cokolwiek"]ukryte[/show]')));
    }

    public function test_theme_block_fields_can_be_bound_to_content_fields(): void
    {
        $registry = app(BlockRegistry::class);
        $this->page->update(['fields' => ['www' => 'https://youtu.be/dQw4w9WgXcQ', 'podtytul' => 'Tytuł z pola']]);
        $attrs = ['url' => 'https://vimeo.com/123456789', 'title' => 'Stary', '__bind' => ['url' => 'www', 'title' => 'podtytul', 'nie-ma-takiego' => 'www', 'ratio' => 'zła nazwa']];

        // Zapis zachowuje tylko poprawne powiązania pól tego bloku.
        $this->assertSame(['url' => 'www', 'title' => 'podtytul'], $registry->normalize('video', $attrs)['__bind']);

        $bound = $registry->render('video', $attrs, $this->page);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $bound);
        $this->assertStringContainsString('title="Tytuł z pola"', $bound);

        // Bez treści (podgląd w edytorze) i przy pustym polu obowiązuje ustawienie bloku.
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $registry->render('video', $attrs));
        $this->page->update(['fields' => []]);
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $registry->render('video', $attrs, $this->page));

        // Pola wbudowane też działają, a powiązanie przechodzi przez renderer strony.
        $this->assertStringContainsString('title="Oferta"', $registry->render('video', ['url' => 'https://youtu.be/dQw4w9WgXcQ', '__bind' => ['title' => '_title']], $this->page));
        $stored = app(BuilderRenderer::class)->render(['pages' => [['frames' => [['component' => ['components' => [
            ['type' => 'lm-block', 'block' => 'video', 'attrs' => $attrs],
        ]]]]]]]);
        $this->assertStringContainsString('[block name="video"', $stored);
        $this->assertStringContainsString('youtube-nocookie', $this->sc('[block name="video" data="'.BlockRegistry::encode(['url' => 'https://youtu.be/dQw4w9WgXcQ']).'"]'));
    }

    public function test_seo_text_skips_theme_blocks_in_description_and_panel_preview(): void
    {
        $block = '[block name="archive-heading" data="eyJzaG93X2Rlc2NyaXB0aW9uIjoxfQ"][block name="gallery" data="eyJjb2x1bW5zIjoiMyJ9"]';
        $this->page->update(['html_cache' => $block.'<p>Prawdziwy akapit o stronie.</p>', 'excerpt' => '']);

        $resolver = app(SeoResolver::class);
        $this->assertSame('Prawdziwy akapit o stronie.', $resolver->description($this->page->fresh()));

        // Strona złożona wyłącznie z bloków wraca do opisu serwisu zamiast pokazywać surowe shortcody.
        $this->page->update(['html_cache' => $block]);
        $this->assertStringNotContainsString('[block', $resolver->description($this->page->fresh()));

        $this->actingAs(User::factory()->create())
            ->get("/cms/content/page/{$this->page->id}/edit")
            ->assertInertia(fn ($p) => $p->where('seoSite.body', ''));
    }
}
