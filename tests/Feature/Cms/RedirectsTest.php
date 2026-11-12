<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Redirects\NotFoundLog;
use App\Cms\Redirects\Redirect;
use App\Cms\Redirects\RedirectManager;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RedirectsTest extends TestCase
{
    use RefreshDatabase;

    private RedirectManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->manager = app(RedirectManager::class);
    }

    private function page(string $title, array $attrs = [], string $type = 'page'): Content
    {
        return Content::create($attrs + [
            'content_type_id' => ContentType::where('key', $type)->value('id'),
            'title' => $title, 'slug' => str($title)->slug(), 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);
    }

    public function test_normalize_handles_case_slashes_query_and_own_domain(): void
    {
        $this->assertSame('/stara/strona', $this->manager->normalize('Stara/Strona/?a=1#x'));
        $this->assertSame('/stara', $this->manager->normalize(url('/Stara/')));
        $this->expectException(InvalidArgumentException::class);
        $this->manager->normalize('https://inna-domena.example/x');
    }

    public function test_public_request_is_redirected_with_code_and_query_string_and_counts_hits(): void
    {
        $this->manager->create('/stara', '/nowa', 301);
        $this->manager->create('/tymczasowa', 'https://example.com/x', 302);

        $this->get('/Stara/?utm=1')->assertStatus(301)->assertRedirect('/nowa?utm=1');
        $this->get('/tymczasowa?a=b')->assertStatus(302)->assertRedirect('https://example.com/x');
        $this->assertSame(1, Redirect::firstWhere('source', '/stara')->hits);
        $this->assertNotNull(Redirect::firstWhere('source', '/stara')->last_hit_at);
    }

    public function test_gone_inactive_and_unknown(): void
    {
        $this->manager->create('/usunieta', null, 410);
        $off = $this->manager->create('/wylaczona', '/gdzies', 301);
        $off->update(['active' => false]);

        $this->get('/usunieta')->assertStatus(410)->assertSee('trwale usunięta');
        $this->get('/wylaczona')->assertStatus(404);
    }

    public function test_redirect_takes_precedence_over_content_at_same_address(): void
    {
        $this->page('Oferta');
        $this->manager->create('/oferta', '/inna', 301);

        $this->get('/oferta')->assertRedirect('/inna');
    }

    public function test_validation_rejects_reserved_loops_duplicates_and_bad_targets(): void
    {
        $this->manager->create('/a', '/b');

        foreach ([['/cms/x', '/y'], ['/', '/y'], ['/a', '/z'], ['/c', 'javascript:alert(1)'], ['/c', '//evil.example'], ['/c', 'nie-sciezka'], ['/b', '/a']] as [$source, $target]) {
            try {
                $this->manager->create($source, $target);
                $this->fail("Powinno odrzucić {$source} → {$target}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_chains_are_flattened_in_both_directions(): void
    {
        $this->manager->create('/a', '/b');
        $this->manager->create('/b', '/c');

        $this->assertSame('/c', Redirect::firstWhere('source', '/a')->target);

        $this->manager->create('/z', '/a');
        $this->assertSame('/c', Redirect::firstWhere('source', '/z')->target);
    }

    public function test_slug_change_creates_automatic_redirect_and_children_follow(): void
    {
        $user = User::factory()->create();
        $parent = $this->page('Oferta');
        $child = $this->page('Korepetycje', ['parent_id' => $parent->id]);

        $this->actingAs($user)->put("/cms/content/page/{$parent->id}", ['title' => 'Oferta', 'slug' => 'uslugi', 'status' => 'published', 'fields' => []])->assertRedirect();

        $this->assertSame('/uslugi', Redirect::firstWhere('source', '/oferta')->target);
        $this->assertTrue(Redirect::firstWhere('source', '/oferta')->auto);
        $this->assertSame('/uslugi/korepetycje', Redirect::firstWhere('source', '/oferta/korepetycje')->target);

        $this->get('/oferta/korepetycje')->assertStatus(301)->assertRedirect('/uslugi/korepetycje');
        $this->get('/uslugi/korepetycje')->assertOk();
    }

    public function test_drafts_get_no_redirect_and_returning_to_old_slug_removes_automatic_one(): void
    {
        $user = User::factory()->create();
        $draft = $this->page('Szkic', ['status' => 'draft']);
        $this->actingAs($user)->put("/cms/content/page/{$draft->id}", ['title' => 'Szkic', 'slug' => 'inny', 'status' => 'draft', 'fields' => []]);
        $this->assertSame(0, Redirect::count());

        $page = $this->page('Strona');
        $update = fn (string $slug) => $this->actingAs($user)->put("/cms/content/page/{$page->id}", ['title' => 'Strona', 'slug' => $slug, 'status' => 'published', 'fields' => []]);

        $update('nowy');
        $this->assertSame(1, Redirect::where('source', '/strona')->count());

        $update('strona');
        $this->assertSame(0, Redirect::where('source', '/strona')->count());
        $this->get('/strona')->assertOk();
    }

    public function test_default_404_page_logs_misses_and_ignores_admin_paths(): void
    {
        $this->get('/nie-ma-takiej')->assertStatus(404)->assertSee('Nie znaleziono strony')->assertSee('noindex', false);
        $this->get('/nie-ma-takiej')->assertStatus(404);
        $this->get('/cms/nie-ma')->assertStatus(404);

        $this->assertSame(2, NotFoundLog::firstWhere('path', '/nie-ma-takiej')->hits);
        $this->assertNull(NotFoundLog::firstWhere('path', '/cms/nie-ma'));
    }

    public function test_custom_404_page_is_rendered_with_404_status_and_noindex(): void
    {
        $page = $this->page('Ups', ['html_cache' => '<section><h1>Własna strona błędu</h1></section>']);
        app(Settings::class)->save('pages', ['not_found_content_id' => $page->id]);

        $this->get('/brak')->assertStatus(404)->assertSee('Własna strona błędu')->assertSee('noindex, nofollow', false)->assertDontSee('rel="canonical"', false);
    }

    public function test_admin_crud_validates_and_requires_login(): void
    {
        $this->get('/cms/redirects')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/cms/redirects')->assertOk();
        $this->post('/cms/redirects', ['source' => '/x', 'target' => '/y', 'status_code' => 301])->assertRedirect();
        $this->post('/cms/redirects', ['source' => '/x', 'target' => '/z', 'status_code' => 301])->assertSessionHasErrors('source');
        $this->post('/cms/redirects', ['source' => '/q', 'target' => 'zle', 'status_code' => 301])->assertSessionHasErrors('target');
        $this->post('/cms/redirects', ['source' => '/q', 'target' => '/r', 'status_code' => 399])->assertSessionHasErrors('status_code');

        $r = Redirect::firstWhere('source', '/x');
        $this->put("/cms/redirects/{$r->id}", ['source' => '/x2', 'target' => '/y', 'status_code' => 302, 'active' => false])->assertRedirect();
        $this->assertSame([302, false], [$r->fresh()->status_code, $r->fresh()->active]);

        $this->post("/cms/redirects/{$r->id}/toggle");
        $this->assertTrue($r->fresh()->active);
        $this->delete("/cms/redirects/{$r->id}");
        $this->assertSame(0, Redirect::count());
    }

    public function test_creating_redirect_removes_path_from_404_monitor_and_logs_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $this->get('/zgubiona');
        $this->get('/inna-zgubiona');

        $this->actingAs($user)->post('/cms/redirects', ['source' => '/zgubiona', 'target' => '/', 'status_code' => 301])->assertRedirect();
        $this->assertNull(NotFoundLog::firstWhere('path', '/zgubiona'));
        $this->assertSame(1, NotFoundLog::count());

        $this->actingAs($user)->delete('/cms/redirects/logs')->assertRedirect();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_404_page_setting_validates_existing_content(): void
    {
        $user = User::factory()->create();
        $page = $this->page('Błąd');

        $this->actingAs($user)->put('/cms/redirects/pages', ['not_found_content_id' => 9999])->assertSessionHasErrors('not_found_content_id');
        $this->actingAs($user)->put('/cms/redirects/pages', ['not_found_content_id' => $page->id])->assertRedirect();
        $this->assertSame($page->id, app(Settings::class)->get('pages', 'not_found_content_id'));
    }
}
