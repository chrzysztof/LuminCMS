<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BuilderRenderer;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Media\Media;
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
            ->has('customFields', 5) // bez repeatera
            ->where('customFields.0.name', 'podtytul')
            ->where('customFields.0.preview', 'Wartość z pola'));
    }

    public function test_seo_description_uses_field_values_not_raw_shortcodes(): void
    {
        $this->page->update(['html_cache' => '<h2>[field name="podtytul" format="text"]Zastępczy[/field]</h2>']);

        $this->get('/oferta')->assertOk()
            ->assertSee('Wartość', false)
            ->assertDontSee('[field', false);
    }
}
