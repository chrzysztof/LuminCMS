<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostsListBlockTest extends TestCase
{
    use RefreshDatabase;

    private Content $first;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);

        $tax = Taxonomy::where('key', 'category')->firstOrFail();
        $news = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Nowości', 'slug' => 'nowosci']);
        $tips = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Porady', 'slug' => 'porady']);
        Term::create(['taxonomy_id' => $tax->id, 'name' => 'Pusta', 'slug' => 'pusta']);

        $this->first = $this->makePost('Alfa', 'alfa', 1, 'Zajawka alfa');
        $this->first->terms()->attach($news);
        $this->makePost('Beta', 'beta', 2)->terms()->attach($tips);
        $this->makePost('Gamma', 'gamma', 3)->terms()->attach($news);
        $this->makePost('Szkic', 'szkic', 1, null, ['status' => 'draft']);
    }

    private function makePost(string $title, string $slug, int $daysAgo, ?string $excerpt = null, array $extra = []): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => $title, 'slug' => $slug, 'status' => 'published',
            'published_at' => now()->subDays($daysAgo), 'excerpt' => $excerpt, 'fields' => [], ...$extra,
        ]);
    }

    private function render(array $attrs = [], ?Content $current = null): string
    {
        return app(BlockRegistry::class)->render('posts-list', $attrs, $current);
    }

    public function test_lists_published_posts_newest_first_with_limit(): void
    {
        $out = $this->render(['type' => 'post', 'limit' => 2]);

        $this->assertSame(2, substr_count($out, '<article'));
        $this->assertStringContainsString('Alfa', $out);
        $this->assertStringContainsString('Beta', $out);
        $this->assertStringNotContainsString('Gamma', $out);
        $this->assertStringNotContainsString('Szkic', $out);
        $this->assertLessThan(strpos($out, 'Beta'), strpos($out, 'Alfa'));
        $this->assertStringContainsString('/blog/alfa', $out);
    }

    public function test_sorting_and_editor_term_filter(): void
    {
        $asc = $this->render(['type' => 'post', 'order' => 'asc']);
        $this->assertLessThan(strpos($asc, 'Alfa'), strpos($asc, 'Gamma'));

        $byTitle = $this->render(['type' => 'post', 'orderby' => 'title', 'order' => 'desc']);
        $this->assertLessThan(strpos($byTitle, 'Alfa'), strpos($byTitle, 'Gamma'));

        $filtered = $this->render(['type' => 'post', 'term' => 'category:nowosci']);
        $this->assertStringContainsString('Alfa', $filtered);
        $this->assertStringContainsString('Gamma', $filtered);
        $this->assertStringNotContainsString('Beta', $filtered);
    }

    public function test_visitor_filter_shows_only_terms_with_posts_and_filters_by_query(): void
    {
        $attrs = ['type' => 'post', 'filter_taxonomy' => 'category'];
        $all = $this->render($attrs);

        $this->assertStringContainsString('lm-posts-block__filters', $all);
        $this->assertStringContainsString('Porady', $all);
        $this->assertStringNotContainsString('>Pusta<', $all);

        $this->get('/'); // zwykłe żądanie, bez filtra
        $this->app['request']->query->set('filtr', 'porady');
        $filtered = $this->render($attrs);

        $this->assertStringContainsString('Beta', $filtered);
        $this->assertStringNotContainsString('Alfa', $filtered);
        $this->assertStringContainsString('aria-current="true">Porady', $filtered);

        $this->app['request']->query->set('filtr', 'nie-ma');
        $this->assertStringContainsString('Alfa', $this->render($attrs));
    }

    public function test_current_content_is_excluded_and_options_toggle_parts(): void
    {
        $out = $this->render(['type' => 'post', 'exclude_current' => true], $this->first);
        $this->assertStringNotContainsString('Alfa', $out);
        $this->assertStringContainsString('Beta', $out);

        $full = $this->render(['type' => 'post', 'show_excerpt' => true, 'show_date' => true]);
        $this->assertStringContainsString('Zajawka alfa', $full);
        $this->assertStringContainsString('<time', $full);

        $bare = $this->render(['type' => 'post']);
        $this->assertStringNotContainsString('Zajawka alfa', $bare);
        $this->assertStringNotContainsString('<time', $bare);

        $this->assertStringContainsString('lm-posts-block__items--list', $this->render(['type' => 'post', 'layout' => 'list']));
        $this->assertStringContainsString('lm-posts-block__items--cols-3', $this->render(['type' => 'post', 'columns' => 'zly']));
    }

    public function test_unknown_type_is_rejected_and_choices_come_from_the_site(): void
    {
        // Nieznany typ jest odrzucany przy zapisie, a pozostałe ustawienia bloku zostają.
        $clean = app(BlockRegistry::class)->normalize('posts-list', ['type' => 'nie-ma', 'limit' => 5]);
        $this->assertArrayNotHasKey('type', $clean);
        $this->assertSame(5, (int) $clean['limit']);

        ContentType::create(['key' => 'oferta', 'label' => 'Oferta', 'label_plural' => 'Oferty']);
        $this->assertStringContainsString(__('Brak wpisów.'), $this->render(['type' => 'oferta']));

        $def = collect(app(BlockRegistry::class)->definitions())->firstWhere('name', 'posts-list');
        $type = collect($def['fields'])->firstWhere('name', 'type');
        $term = collect($def['fields'])->firstWhere('name', 'term');

        $this->assertArrayHasKey('post', $type['choices']);
        $this->assertArrayNotHasKey('part', $type['choices']);
        $this->assertArrayHasKey('category:nowosci', $term['choices']);
    }
}
