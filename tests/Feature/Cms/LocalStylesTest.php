<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BuilderRenderer;
use App\Cms\Builder\LocalStyles;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalStylesTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $components, array $styles): array
    {
        return ['styles' => $styles, 'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => $components]]]]]];
    }

    private function text(string $id, string $text = 'Tekst'): array
    {
        return ['tagName' => 'p', 'type' => 'text', 'attributes' => ['id' => $id], 'components' => [['type' => 'textnode', 'content' => $text]]];
    }

    public function test_local_styles_become_classes_and_css_without_inline_styles(): void
    {
        $renderer = app(BuilderRenderer::class);
        $html = $renderer->render($this->project([$this->text('i1a'), $this->text('i2b'), $this->text('i3c', 'Bez stylu')], [
            ['selectors' => ['#i1a'], 'style' => ['background-color' => '#ff0000', 'padding-top' => '2rem', 'color' => 'white']],
            ['selectors' => ['#i1a'], 'style' => ['padding-top' => '1rem'], 'mediaText' => '(max-width: 900px)'],
            ['selectors' => ['#i2b'], 'style' => ['padding-top' => '2rem', 'color' => 'white', 'background-color' => '#ff0000']],
        ]));

        // Identyczne style dwóch elementów dzielą jedną klasę, element bez stylów jej nie dostaje.
        preg_match_all('/class="(lm-x-[0-9a-f]{8})"/', $html, $m);
        $this->assertCount(2, $m[1]);
        $this->assertStringNotContainsString('id=', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('<p>Bez stylu</p>', $html);

        $css = $renderer->css();
        $this->assertStringContainsString('{background-color:#ff0000;color:white;padding-top:2rem}', $css);
        $this->assertStringContainsString('@media (max-width:900px){', $css);
        $this->assertStringContainsString('padding-top:1rem', $css);
    }

    public function test_only_safe_properties_and_values_are_accepted(): void
    {
        $renderer = app(BuilderRenderer::class);
        $renderer->render($this->project([$this->text('x1')], [
            ['selectors' => ['#x1'], 'style' => [
                'background-color' => 'red;} body{display:none', 'color' => 'expression(alert(1))', 'padding-top' => '1rem', 'margin-left' => '10px', 'position' => 'fixed',
                'background-image' => "url('/storage/a.jpg')", 'width' => '100px', 'margin-top' => 'calc(1px+2px)', 'border-radius' => '8px',
            ]],
            ['selectors' => ['#x1'], 'style' => ['color' => 'red'], 'mediaText' => '(max-width: 1px) and (hover)'],
            ['selectors' => ['#x1'], 'style' => ['color' => 'red'], 'state' => 'hover'],
            ['selectors' => ['.klasa'], 'style' => ['color' => 'red']],
        ]));
        $css = $renderer->css();

        $this->assertStringContainsString("background-image:url('/storage/a.jpg')", $css);
        $this->assertStringContainsString('border-radius:8px', $css);
        $this->assertStringContainsString('margin-left:10px', $css);
        foreach (['body{', 'expression', 'position', 'width:100px', 'calc', 'hover', 'klasa', '@media'] as $bad) {
            $this->assertStringNotContainsString($bad, $css);
        }

        $bad = app(LocalStyles::class)->clean(['background-image' => 'url(javascript:alert(1))', 'background-size' => 'cover', 'color' => 'var(--lm-primary)']);
        $this->assertSame(['background-size' => 'cover', 'color' => 'var(--lm-primary)'], $bad);
    }

    public function test_saved_css_is_served_in_head_for_content_and_parts(): void
    {
        $this->seed(CmsSeeder::class);
        $admin = User::factory()->create();
        $page = Content::create(['content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Stylowana', 'slug' => 'stylowana', 'status' => 'published', 'published_at' => now()]);

        $project = $this->project([$this->text('s1')], [['selectors' => ['#s1'], 'style' => ['background-color' => '#112233']]]);
        $this->actingAs($admin)->putJson("/cms/content/page/{$page->id}/builder", ['project' => $project, 'status' => 'published'])->assertOk();

        $this->assertStringContainsString('background-color:#112233', (string) $page->fresh()->css_cache);
        $this->get('/stylowana')->assertOk()
            ->assertSee('<style id="lm-local-styles">', false)
            ->assertSee('background-color:#112233', false);

        // Strona bez własnych stylów nie dostaje bloku <style>.
        $plain = Content::create(['content_type_id' => $page->content_type_id, 'title' => 'Zwykła', 'slug' => 'zwykla', 'status' => 'published', 'published_at' => now(), 'html_cache' => '<p>x</p>']);
        $this->get('/zwykla')->assertOk()->assertDontSee('lm-local-styles', false);
    }
}
