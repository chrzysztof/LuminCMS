<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BuilderRenderer;
use App\Cms\Media\MediaManager;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuilderRendererTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $components): array
    {
        return ['pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => $components]]]]]];
    }

    private function render(array $components): string
    {
        return app(BuilderRenderer::class)->render($this->project($components));
    }

    public function test_renders_semantic_structure_with_classes(): void
    {
        $html = $this->render([[
            'tagName' => 'section', 'classes' => ['lm-section', ['name' => 'lm-bg-muted'], ['name' => 'off', 'active' => false]],
            'components' => [
                ['tagName' => 'h1', 'type' => 'text', 'components' => [['type' => 'textnode', 'content' => 'Cześć & witaj']]],
                ['tagName' => 'p', 'type' => 'text', 'components' => [['type' => 'textnode', 'content' => 'Tekst']]],
            ],
        ]]);

        $this->assertSame('<section class="lm-section lm-bg-muted"><h1>Cześć &amp; witaj</h1><p>Tekst</p></section>', $html);
    }

    public function test_empty_or_malformed_project_renders_nothing(): void
    {
        $this->assertSame('', app(BuilderRenderer::class)->render([]));
        $this->assertSame('', app(BuilderRenderer::class)->render(['pages' => [['frames' => [[]]]]]));
    }

    public function test_dangerous_tags_attributes_and_urls_are_removed(): void
    {
        $html = $this->render([
            ['tagName' => 'script', 'components' => [['type' => 'textnode', 'content' => 'alert(1)']]],
            ['tagName' => 'iframe', 'attributes' => ['src' => 'https://evil.test']],
            ['tagName' => 'div', 'attributes' => ['onclick' => 'x()', 'style' => 'color:red', 'id' => 'a"b', 'class' => 'lm-card x"y'],
                'components' => [
                    ['tagName' => 'a', 'attributes' => ['href' => ' JaVa	script:alert(1)'], 'components' => [['type' => 'textnode', 'content' => 'zły']]],
                    ['tagName' => 'a', 'attributes' => ['href' => 'https://ok.test', 'target' => '_blank'], 'components' => [['type' => 'textnode', 'content' => 'dobry']]],
                ]],
        ]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('javascript', strtolower($html));
        $this->assertStringContainsString('alert(1)', $html); // treść skryptu zostaje jako zwykły tekst
        $this->assertStringNotContainsString('id=', $html); // losowe id z GrapesJS nie trafiają do HTML
        $this->assertStringContainsString('class="lm-card"', $html);
        $this->assertStringContainsString('<a href="https://ok.test" target="_blank" rel="noopener noreferrer">dobry</a>', $html);
        $this->assertStringContainsString('<a>zły</a>', $html);
    }

    public function test_default_tag_is_inferred_from_component_type(): void
    {
        $html = $this->render([
            ['type' => 'lm-button', 'classes' => ['lm-btn'], 'attributes' => ['href' => '/kontakt'], 'components' => [['type' => 'textnode', 'content' => 'Napisz']]],
            ['type' => 'link', 'attributes' => ['href' => '#'], 'components' => [['type' => 'textnode', 'content' => 'x']]],
        ]);

        $this->assertSame('<a class="lm-btn" href="/kontakt">Napisz</a><a href="#">x</a>', $html);
    }

    public function test_text_content_is_escaped(): void
    {
        $this->assertSame('<p>&lt;b&gt;x&lt;/b&gt;</p>', $this->render([['tagName' => 'p', 'content' => '<b>x</b>']]));
    }

    public function test_void_elements_and_plain_images(): void
    {
        $html = $this->render([['tagName' => 'hr'], ['tagName' => 'img', 'attributes' => ['src' => '/x.png', 'alt' => 'Opis']], ['tagName' => 'img', 'attributes' => ['src' => 'javascript:1']]]);

        // Obraz bez poprawnego adresu (np. javascript:) w ogóle się nie renderuje.
        $this->assertSame('<hr><img src="/x.png" alt="Opis" loading="lazy" decoding="async">', $html);
    }

    public function test_media_image_becomes_picture_with_webp_srcset(): void
    {
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
        $media = app(MediaManager::class)->store(UploadedFile::fake()->image('a.jpg', 2000, 1000), ['alt' => 'Z biblioteki']);

        $html = $this->render([['tagName' => 'img', 'type' => 'image', 'classes' => ['lm-image'], 'attributes' => ['data-media-id' => (string) $media->id, 'data-size' => 'medium']]]);

        $this->assertStringStartsWith('<picture><source type="image/webp"', $html);
        $this->assertStringContainsString('alt="Z biblioteki"', $html);
        $this->assertStringContainsString('class="lm-image"', $html);
        $this->assertStringContainsString('width="768" height="384"', $html);
    }

    public function test_missing_media_renders_nothing(): void
    {
        $this->assertSame('', $this->render([['tagName' => 'img', 'attributes' => ['data-media-id' => '9999']]]));
    }

    public function test_deeply_nested_input_is_cut_off(): void
    {
        $node = ['tagName' => 'div'];
        for ($i = 0; $i < 100; $i++) {
            $node = ['tagName' => 'div', 'components' => [$node]];
        }

        $this->assertLessThanOrEqual(41, substr_count($this->render([$node]), '<div'));
    }
}
