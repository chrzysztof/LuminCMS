<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Menus\Menu;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Cms\Themes\Parts;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
    }

    private function page(string $title, string $slug, array $extra = []): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => $title, 'slug' => $slug,
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], 'html_cache' => '<p>'.$title.'</p>', ...$extra,
        ]);
    }

    private function item(string $type, int $depth, array $extra = []): array
    {
        return ['type' => $type, 'depth' => $depth, 'label' => '', 'new_tab' => false, 'mega' => false, ...$extra];
    }

    private function save(Menu $menu, array $items, array $extra = [])
    {
        return $this->put("/cms/menus/{$menu->id}", ['name' => $menu->name, 'items' => $items] + $extra);
    }

    public function test_menu_is_saved_as_a_tree_and_rendered_multilevel(): void
    {
        $this->actingAs($this->admin());
        $about = $this->page('O nas', 'o-nas');
        $team = $this->page('Zespół', 'zespol');
        $this->post('/cms/menus', ['name' => 'Główne'])->assertRedirect();
        $menu = Menu::first();
        $this->assertSame('glowne', $menu->slug);

        $this->save($menu, [
            $this->item('content', 0, ['content_id' => $about->id]),
            $this->item('content', 1, ['content_id' => $team->id, 'label' => 'Ludzie']),
            $this->item('custom', 0, ['url' => 'https://example.com', 'label' => 'Blog <b>zewn.</b>', 'new_tab' => true]),
        ], ['location' => 'primary'])->assertRedirect();

        $this->assertSame(3, $menu->items()->count());
        $child = $menu->items()->where('type', 'content')->where('content_id', $team->id)->first();
        $this->assertSame($menu->items()->orderBy('position')->first()->id, $child->parent_id);

        $html = app(MenuRenderer::class)->render($menu->refresh(), 'zespol');
        $this->assertStringContainsString('<nav class="lm-menu" aria-label="Główne"', $html);
        $this->assertStringContainsString('lm-menu__sub', $html);
        $this->assertStringContainsString('>Ludzie</a>', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('is-trail', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('Blog &lt;b&gt;zewn.&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>zewn', $html);
    }

    public function test_mega_menu_renders_columns_and_extra_part(): void
    {
        $this->actingAs($this->admin());
        $a = $this->page('Usługa A', 'usluga-a');
        $part = app(Parts::class)->create('Promo');
        $part->update(['html_cache' => '<p>Promocja!</p>']);
        $this->post('/cms/menus', ['name' => 'Mega']);
        $menu = Menu::first();

        $this->save($menu, [
            $this->item('heading', 0, ['label' => 'Oferta', 'mega' => true, 'mega_layout' => 'full', 'mega_part_id' => $part->id]),
            $this->item('heading', 1, ['label' => 'Dla firm']),
            $this->item('content', 2, ['content_id' => $a->id]),
            $this->item('custom', 1, ['label' => 'Cennik', 'url' => '/cennik']),
        ])->assertRedirect();

        $html = app(MenuRenderer::class)->render($menu->refresh(), '');
        $this->assertStringContainsString('is-mega', $html);
        $this->assertStringContainsString('lm-menu__mega--full', $html);
        $this->assertSame(2, substr_count($html, 'class="lm-menu__col"'));
        $this->assertStringContainsString('<span class="lm-menu__col-title">Dla firm</span>', $html);
        $this->assertStringContainsString('<a class="lm-menu__col-title" href="http://localhost/cennik">', str_replace('href="/cennik"', 'href="http://localhost/cennik"', $html));
        $this->assertStringContainsString('<div class="lm-menu__extra"><p>Promocja!</p></div>', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
    }

    public function test_unpublished_or_deleted_targets_are_skipped(): void
    {
        $this->actingAs($this->admin());
        $live = $this->page('Żywa', 'zywa');
        $draft = $this->page('Szkic', 'szkic');
        $this->post('/cms/menus', ['name' => 'M']);
        $menu = Menu::first();
        $this->save($menu, [$this->item('content', 0, ['content_id' => $live->id]), $this->item('content', 0, ['content_id' => $draft->id])]);

        $draft->update(['status' => 'draft']);
        $html = app(MenuRenderer::class)->render($menu->refresh(), '');
        $this->assertStringContainsString('Żywa', $html);
        $this->assertStringNotContainsString('Szkic', $html);

        $live->forceDelete();
        $this->assertSame('', app(MenuRenderer::class)->render($menu->refresh(), ''));
    }

    public function test_term_items_link_to_term_pages(): void
    {
        $this->actingAs($this->admin());
        $term = Term::create(['taxonomy_id' => Taxonomy::where('key', 'category')->value('id'), 'name' => 'Nowości', 'slug' => 'nowosci']);
        $this->post('/cms/menus', ['name' => 'M']);
        $menu = Menu::first();
        $this->save($menu, [$this->item('term', 0, ['term_id' => $term->id])])->assertRedirect();

        $this->assertStringContainsString('href="'.url('/kategoria/nowosci').'"', app(MenuRenderer::class)->render($menu->refresh(), ''));
    }

    public function test_validation_rejects_unsafe_urls_bad_depth_and_missing_targets(): void
    {
        $this->actingAs($this->admin());
        $this->post('/cms/menus', ['name' => 'M']);
        $menu = Menu::first();

        $this->save($menu, [$this->item('custom', 0, ['label' => 'X', 'url' => 'javascript:alert(1)'])])->assertSessionHasErrors('items.0.url');
        $this->save($menu, [$this->item('custom', 0, ['label' => 'X', 'url' => '//evil.example'])])->assertSessionHasErrors('items.0.url');
        $this->save($menu, [$this->item('heading', 1, ['label' => 'X'])])->assertSessionHasErrors('items.0.depth');
        $this->save($menu, [$this->item('heading', 0, ['label' => 'A']), $this->item('heading', 2, ['label' => 'B'])])->assertSessionHasErrors('items.1.depth');
        $this->save($menu, [$this->item('content', 0)])->assertSessionHasErrors('items.0.content_id');
        $this->save($menu, [$this->item('heading', 0)])->assertSessionHasErrors('items.0.label');
        $this->save($menu, [], ['location' => 'nie-ma'])->assertSessionHasErrors('location');
        $this->assertSame(0, $menu->items()->count());
    }

    public function test_location_moves_to_the_menu_saved_last(): void
    {
        $this->actingAs($this->admin());
        $this->post('/cms/menus', ['name' => 'Jeden']);
        $this->post('/cms/menus', ['name' => 'Dwa']);
        [$one, $two] = [Menu::where('slug', 'jeden')->first(), Menu::where('slug', 'dwa')->first()];

        $this->save($one, [], ['location' => 'primary'])->assertRedirect();
        $this->save($two, [], ['location' => 'primary'])->assertRedirect();

        $this->assertNull($one->refresh()->location);
        $this->assertSame('primary', $two->refresh()->location);
    }

    public function test_menu_shortcode_and_default_header_render_the_menu(): void
    {
        $this->actingAs($this->admin());
        $page = $this->page('Start', 'start');
        $this->post('/cms/menus', ['name' => 'Główne']);
        $menu = Menu::first();
        $this->save($menu, [$this->item('content', 0, ['content_id' => $page->id])], ['location' => 'primary']);

        $this->assertStringContainsString('lm-menu__link', app(Shortcodes::class)->render('[menu name="glowne"]'));
        $this->assertStringContainsString('lm-menu__link', app(Shortcodes::class)->render('[menu location="primary"]'));
        $this->assertSame('', app(Shortcodes::class)->render('[menu name="nie-ma"]'));

        $this->get('/start')->assertOk()->assertSee('data-lm-menu', false)->assertSee('menu.js', false);
    }

    public function test_global_part_replaces_default_header_and_is_not_public(): void
    {
        $this->actingAs($this->admin());
        $parts = app(Parts::class);

        $this->page('Strona', 'strona');
        $this->get('/strona')->assertSee('lm-site-header__brand', false);

        $part = $parts->create('Nagłówek', 'header');
        $part->update(['html_cache' => '<div>Mój nagłówek [button url="/x"]Klik[/button]</div>']);

        $this->get('/strona')->assertOk()->assertSee('Mój nagłówek', false)->assertSee('lm-btn', false)->assertDontSee('lm-site-header__brand', false);

        // Brak publicznego adresu i wpisu w sitemap.
        $this->get('/'.$part->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($part->slug, false);

        // Szablon landing nadal ukrywa nagłówek i stopkę.
        $page = Content::where('slug', 'strona')->first();
        $page->update(['template' => 'landing']);
        $this->get('/strona')->assertDontSee('Mój nagłówek', false);

        // Przywrócenie domyślnego wyglądu.
        $this->put('/cms/parts/slots/header', ['content_id' => null])->assertRedirect();
        $page->update(['template' => null]);
        $this->get('/strona')->assertSee('lm-site-header__brand', false);
    }

    public function test_parts_panel_assign_create_delete_and_authorization(): void
    {
        $this->actingAs($this->admin());

        $this->get('/cms/parts')->assertOk()->assertInertia(fn ($p) => $p->component('parts/index')->has('slots', 2)->has('parts', 0));

        $response = $this->post('/cms/parts', ['title' => 'Stopka', 'slot' => 'footer']);
        $part = Content::where('title', 'Stopka')->firstOrFail();
        $response->assertRedirect("/cms/content/part/{$part->id}/builder");
        $this->assertSame($part->id, app(Parts::class)->assignedId('footer'));
        $this->post('/cms/parts', ['title' => 'X', 'slot' => 'nie-ma'])->assertSessionHasErrors('slot');
        $this->put('/cms/parts/slots/nie-ma', ['content_id' => null])->assertNotFound();
        $this->put('/cms/parts/slots/header', ['content_id' => $this->page('P', 'p')->id])->assertSessionHasErrors('content_id');

        $this->delete("/cms/parts/{$part->id}")->assertRedirect();
        $this->assertNull(app(Parts::class)->assignedId('footer'));
        $this->assertSoftDeleted($part);
    }

    public function test_only_administrators_manage_menus_and_parts(): void
    {
        $this->actingAs(User::factory()->role('editor')->create());
        $part = app(Parts::class)->create('Nagłówek', 'header');

        $this->get('/cms/menus')->assertForbidden();
        $this->post('/cms/menus', ['name' => 'X'])->assertForbidden();
        $this->get('/cms/parts')->assertForbidden();
        // Edytor treści nie otworzy części w edytorze wizualnym ani nie wpisze jej w sidebarze.
        $this->get("/cms/content/part/{$part->id}/builder")->assertForbidden();
        $this->get('/cms/content/page')->assertOk()->assertInertia(fn ($p) => $p->where('contentTypes', fn ($t) => collect($t)->pluck('key')->doesntContain('part')));
    }

    public function test_menu_editor_page_lists_sources_and_flat_items(): void
    {
        $this->actingAs($this->admin());
        $page = $this->page('O nas', 'o-nas');
        $this->post('/cms/menus', ['name' => 'M']);
        $menu = Menu::first();
        $this->save($menu, [$this->item('content', 0, ['content_id' => $page->id]), $this->item('heading', 1, ['label' => 'Pod'])]);

        $this->get("/cms/menus?menu={$menu->id}")->assertOk()->assertInertia(fn ($p) => $p
            ->component('menus/index')
            ->where('menu.items.0.depth', 0)->where('menu.items.1.depth', 1)->where('menu.items.1.label', 'Pod')
            ->where('sources.contents.0.title', 'O nas')->has('locations', 2));

        $this->delete("/cms/menus/{$menu->id}")->assertRedirect('/cms/menus');
        $this->assertSame(0, Menu::count());
    }
}
