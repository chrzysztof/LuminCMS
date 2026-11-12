<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortcodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function render(string $text, ?Content $current = null): string
    {
        return app(Shortcodes::class)->render($text, $current);
    }

    private function makePost(string $title, string $slug, array $extra = []): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => $title, 'slug' => $slug,
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], ...$extra,
        ]);
    }

    public function test_simple_and_attribute_shortcodes(): void
    {
        $this->assertSame('© '.date('Y'), $this->render('© [year]'));
        $this->assertSame(date('Y'), $this->render('[date format="Y"]'));
        $this->assertSame(now()->format('Y-m'), $this->render("[date format='Y-m']"));
        $this->assertSame(now()->format('d/m'), $this->render('[date format=d/m]'));
    }

    public function test_enclosing_and_self_closing_forms(): void
    {
        $sc = app(Shortcodes::class);
        $sc->add('box', fn (array $a, string $c) => '<div class="'.e($a['class'] ?? '').'">'.$c.'</div>');

        $this->assertSame('<div class="x">hej '.date('Y').'</div>', $this->render('[box class="x"]hej [year][/box]'));
        $this->assertSame('<div class=""></div>', $this->render('[box /]'));
        $this->assertSame('<div class="a">1</div> i <div class="b">2</div>', $this->render('[box class="a"]1[/box] i [box class="b"]2[/box]'));
    }

    public function test_unknown_escaped_and_code_blocks_stay_untouched(): void
    {
        $this->assertSame('[nieznany x=1]', $this->render('[nieznany x=1]'));
        $this->assertSame('[year]', $this->render('[[year]]'));
        $this->assertSame('<code>[year]</code> '.date('Y'), $this->render('<code>[year]</code> [year]'));
        $this->assertSame("<pre>\n[year]\n</pre>", $this->render("<pre>\n[year]\n</pre>"));
    }

    public function test_typographic_quotes_from_editors_are_understood(): void
    {
        $out = $this->render('[button url=“/kontakt”]Napisz[/button]');

        $this->assertSame('<a class="lm-btn" href="/kontakt">Napisz</a>', $out);
    }

    public function test_button_rejects_dangerous_urls_and_escapes(): void
    {
        $this->assertSame('', $this->render('[button url="javascript:alert(1)"]x[/button]'));
        $this->assertSame('', $this->render('[button url="//evil.test"]x[/button]'));
        $this->assertSame(
            '<a class="lm-btn lm-btn-secondary" href="https://a.test/?x=1&amp;y=2" target="_blank" rel="noopener noreferrer">T</a>',
            $this->render('[button url="https://a.test/?x=1&y=2" style="secondary" new="1"]<u>T</u>[/button]'),
        );
    }

    public function test_posts_lists_published_items_filtered_by_term(): void
    {
        $tax = Taxonomy::where('key', 'category')->firstOrFail();
        $news = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Nowości', 'slug' => 'nowosci']);
        $a = $this->makePost('Pierwszy <b>', 'pierwszy');
        $this->makePost('Drugi', 'drugi');
        $this->makePost('Szkic', 'szkic', ['status' => 'draft', 'published_at' => null]);
        $a->terms()->attach($news);

        $all = $this->render('[posts]');
        $this->assertStringContainsString('Drugi', $all);
        $this->assertStringContainsString('Pierwszy &lt;b&gt;', $all);
        $this->assertStringNotContainsString('Szkic', $all);
        $this->assertStringContainsString('/blog/pierwszy', $all);

        $filtered = $this->render('[posts term="nowosci" taxonomy="category"]');
        $this->assertStringContainsString('Pierwszy', $filtered);
        $this->assertStringNotContainsString('Drugi', $filtered);

        $this->assertSame('', $this->render('[posts type="nie-ma"]'));
        $this->assertSame(1, substr_count($this->render('[posts limit="1"]'), '<li>'));
    }

    public function test_link_and_field_shortcodes(): void
    {
        $post = $this->makePost('Cel', 'cel');

        $this->assertSame('<a href="'.url('/blog/cel').'">Cel</a>', $this->render("[link id=\"{$post->id}\"]"));
        $this->assertSame('tekst', $this->render('[link id="999999"]tekst[/link]'));
        $this->assertSame('', $this->render('[field name="brak"]', $post));
    }

    public function test_public_page_renders_shortcodes(): void
    {
        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Home', 'slug' => 'home',
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [],
            'html_cache' => '<p>Rok: [year]</p><p>[[year]]</p>',
        ]);

        $this->get('/')->assertOk()->assertSee('Rok: '.date('Y'), false)->assertSee('[year]', false);
    }

    public function test_reference_page_lists_built_in_and_custom_shortcodes(): void
    {
        app(Shortcodes::class)->add('hello', fn () => 'hi', '[hello name="x"]', 'Opis własnego kodu');

        $this->actingAs(User::factory()->role('contributor')->create());

        $this->get('/cms/shortcodes')->assertOk()->assertInertia(fn ($page) => $page
            ->component('shortcodes/index')
            ->has('shortcodes', 12)
            ->where('shortcodes.0.name', 'block')
            ->where('shortcodes.0.usage', fn ($u) => str_starts_with($u, '[block'))
            ->where('shortcodes.0.description', fn ($d) => $d !== ''));

        $this->actingAs(User::factory()->role('subscriber')->create());
        $this->get('/cms/shortcodes')->assertForbidden();
    }
}
