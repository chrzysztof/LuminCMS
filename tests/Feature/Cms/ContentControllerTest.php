<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->user = User::factory()->create();

        FieldGroup::create([
            'key' => 'group_oferta', 'title' => 'Oferta',
            'fields' => [
                ['type' => 'text', 'name' => 'podtytul', 'label' => 'Podtytuł', 'required' => true],
                ['type' => 'password', 'name' => 'sekret'],
                ['type' => 'true_false', 'name' => 'promocja'],
                ['type' => 'number', 'name' => 'cena_promo', 'required' => true,
                    'conditional_logic' => [[['field' => 'promocja', 'operator' => '==', 'value' => '1']]]],
            ],
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
        ]);
    }

    private function page(array $attributes = []): Content
    {
        return Content::create($attributes + [
            'content_type_id' => ContentType::where('key', 'page')->value('id'),
            'title' => 'Oferta', 'slug' => 'oferta', 'status' => 'draft', 'fields' => [],
        ]);
    }

    public function test_body_is_saved_sanitized_and_skipped_when_builder_project_exists(): void
    {
        $this->actingAs($this->user);
        $type = ContentType::where('key', 'page')->firstOrFail();

        $this->post('/cms/content/page', [
            'title' => 'Z treścią', 'status' => 'draft', 'body' => '<p>Cześć</p><script>alert(1)</script>',
            'fields' => ['podtytul' => 'x'],
        ])->assertSessionHasNoErrors();

        $c = Content::where('title', 'Z treścią')->firstOrFail();
        $this->assertStringContainsString('<p>Cześć</p>', $c->html_cache);
        $this->assertStringNotContainsString('script', $c->html_cache);

        $c->update(['builder_data' => ['pages' => []], 'html_cache' => '<section>Builder</section>']);
        $this->put("/cms/content/page/{$c->id}", [
            'title' => 'Z treścią', 'status' => 'draft', 'body' => '<p>Nadpisane</p>',
            'fields' => ['podtytul' => 'x'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('<section>Builder</section>', $c->fresh()->html_cache);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/cms/content/page')->assertRedirect('/login');
    }

    public function test_index_lists_content_with_counts_and_filters(): void
    {
        $this->page(['title' => 'Kontakt', 'slug' => 'kontakt']);
        $this->page(['title' => 'Oferta', 'status' => 'published']);

        $this->actingAs($this->user)->get('/cms/content/page?status=published')
            ->assertInertia(fn (Assert $p) => $p->component('content/index')
                ->where('counts.all', 2)->where('counts.published', 1)->where('counts.draft', 1)
                ->has('items.data', 1)->where('items.data.0.title', 'Oferta'));
    }

    public function test_index_filters_by_taxonomy_term_with_descendants_and_by_author(): void
    {
        $cat = Taxonomy::where('key', 'category')->firstOrFail();
        $news = Term::create(['taxonomy_id' => $cat->id, 'name' => 'Newsy', 'slug' => 'newsy']);
        $sub = Term::create(['taxonomy_id' => $cat->id, 'parent_id' => $news->id, 'name' => 'Branża', 'slug' => 'branza']);
        $other = Term::create(['taxonomy_id' => $cat->id, 'name' => 'Inne', 'slug' => 'inne']);
        $ada = User::factory()->create(['name' => 'Ada']);

        $postType = ContentType::where('key', 'post')->value('id');
        $mk = fn (string $title, $term, $author) => tap(Content::create([
            'content_type_id' => $postType, 'title' => $title, 'slug' => str($title)->slug(), 'status' => 'published',
            'published_at' => now(), 'author_id' => $author->id,
        ]), fn (Content $c) => $c->terms()->attach($term->id));
        $mk('W newsach', $news, $this->user);
        $mk('W podkategorii', $sub, $ada);
        $mk('W innych', $other, $ada);

        $titles = fn (string $query) => $this->actingAs($this->user)->get('/cms/content/post'.$query)->viewData('page')['props']['items']['data'];

        $this->assertCount(3, $titles(''));
        // Termin nadrzędny obejmuje potomków.
        $this->assertEqualsCanonicalizing(['W newsach', 'W podkategorii'], array_column($titles("?terms[category]={$news->id}"), 'title'));
        $this->assertSame(['W podkategorii'], array_column($titles("?terms[category]={$sub->id}"), 'title'));
        $this->assertEqualsCanonicalizing(['W podkategorii', 'W innych'], array_column($titles("?author={$ada->id}"), 'title'));
        $this->assertSame(['W podkategorii'], array_column($titles("?author={$ada->id}&terms[category]={$news->id}"), 'title'));
        // Nieznany termin jest ignorowany, a liczniki uwzględniają filtry.
        $this->assertCount(3, $titles('?terms[category]=9999'));
        $this->actingAs($this->user)->get("/cms/content/post?terms[category]={$other->id}")
            ->assertInertia(fn (Assert $p) => $p->where('counts.all', 1)->where('filters.terms.category', $other->id)
                ->has('taxonomies', 2)->where('taxonomies.0.key', 'category')->has('taxonomies.0.terms', 3)
                ->where('taxonomies.0.terms.2.depth', 1)->has('authors', 2));
    }

    public function test_authors_without_edit_others_only_see_their_own_content(): void
    {
        $author = User::factory()->role('author')->create();
        $this->page(['title' => 'Cudza']);
        Content::create(['content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => 'Moja', 'slug' => 'moja', 'status' => 'published', 'published_at' => now(), 'author_id' => $author->id]);

        $this->actingAs($author)->get("/cms/content/post?author={$this->user->id}")
            ->assertInertia(fn (Assert $p) => $p->has('items.data', 1)->where('items.data.0.title', 'Moja')->has('authors', 0)->where('filters.author', ''));
    }

    public function test_shared_props_include_content_types_for_sidebar(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn (Assert $p) => $p->has('contentTypes', 2)->where('contentTypes.0.key', 'page'));
    }

    public function test_create_form_receives_field_groups_for_the_type_only(): void
    {
        $this->actingAs($this->user)->get('/cms/content/page/create')
            ->assertInertia(fn (Assert $p) => $p->component('content/edit')->has('groups', 1)->where('groups.0.fields.0.name', 'podtytul'));

        $this->actingAs($this->user)->get('/cms/content/post/create')
            ->assertInertia(fn (Assert $p) => $p->has('groups', 0));
    }

    public function test_store_creates_content_with_unique_slug_and_validated_fields(): void
    {
        $this->actingAs($this->user)->post('/cms/content/page', [
            'title' => 'Oferta korepetycji', 'status' => 'published', 'fields' => ['podtytul' => 'Online'],
        ])->assertRedirect();

        $content = Content::where('slug', 'oferta-korepetycji')->firstOrFail();
        $this->assertSame($this->user->id, $content->author_id);
        $this->assertSame('Online', $content->fields['podtytul']);
        $this->assertNotNull($content->published_at);

        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'Oferta korepetycji', 'status' => 'draft', 'fields' => ['podtytul' => 'x']]);
        $this->assertDatabaseHas('contents', ['slug' => 'oferta-korepetycji-2']);
    }

    public function test_required_and_conditional_fields_are_validated_on_the_server(): void
    {
        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'A', 'status' => 'draft', 'fields' => []])
            ->assertSessionHasErrors('fields.podtytul');

        // cena_promo wymagana tylko przy włączonej promocji
        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'B', 'status' => 'draft', 'fields' => ['podtytul' => 'x', 'promocja' => true]])
            ->assertSessionHasErrors('fields.cena_promo');

        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'C', 'status' => 'draft', 'fields' => ['podtytul' => 'x', 'promocja' => false]])
            ->assertSessionHasNoErrors();
    }

    public function test_scheduled_status_requires_a_date(): void
    {
        $this->actingAs($this->user)->post('/cms/content/post', ['title' => 'Plan', 'status' => 'scheduled'])
            ->assertSessionHasErrors('published_at');
    }

    public function test_update_changes_content_and_keeps_password_when_left_blank(): void
    {
        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'Oferta', 'status' => 'draft', 'fields' => ['podtytul' => 'x', 'sekret' => 'tajne']]);
        $content = Content::firstOrFail();
        $stored = $content->fields['sekret'];

        $this->actingAs($this->user)->put("/cms/content/page/{$content->id}", ['title' => 'Nowy tytuł', 'slug' => 'nowy', 'status' => 'published', 'fields' => ['podtytul' => 'y', 'sekret' => '']])
            ->assertRedirect();

        $content->refresh();
        $this->assertSame(['Nowy tytuł', 'nowy', 'published', $stored], [$content->title, $content->slug, $content->status, $content->fields['sekret']]);
    }

    public function test_edit_form_does_not_expose_password_ciphertext(): void
    {
        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'Oferta', 'status' => 'draft', 'fields' => ['podtytul' => 'x', 'sekret' => 'tajne']]);
        $content = Content::firstOrFail();

        $this->actingAs($this->user)->get("/cms/content/page/{$content->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('values.podtytul', 'x')->missing('values.sekret'));
    }

    public function test_content_of_another_type_is_not_reachable_through_wrong_type(): void
    {
        $page = $this->page();

        $this->actingAs($this->user)->get("/cms/content/post/{$page->id}/edit")->assertNotFound();
        $this->actingAs($this->user)->delete("/cms/content/post/{$page->id}")->assertNotFound();
    }

    public function test_parent_must_belong_to_the_same_type_and_not_be_self(): void
    {
        $page = $this->page();
        $post = Content::create(['content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => 'P', 'slug' => 'p', 'status' => 'draft']);
        $payload = ['title' => 'Oferta', 'status' => 'draft', 'fields' => ['podtytul' => 'x']];

        $this->actingAs($this->user)->put("/cms/content/page/{$page->id}", $payload + ['parent_id' => $post->id])->assertSessionHasErrors('parent_id');
        $this->actingAs($this->user)->put("/cms/content/page/{$page->id}", $payload + ['parent_id' => $page->id])->assertSessionHasErrors('parent_id');
    }

    public function test_destroy_soft_deletes(): void
    {
        $page = $this->page();

        $this->actingAs($this->user)->delete("/cms/content/page/{$page->id}")->assertRedirect('/cms/content/page');
        $this->assertSoftDeleted('contents', ['id' => $page->id]);
    }

    public function test_dashboard_shows_stats(): void
    {
        $this->page(['status' => 'published']);
        $this->page(['title' => 'B', 'slug' => 'b']);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn (Assert $p) => $p->where('stats.published', 1)->where('stats.drafts', 1)->has('recent', 2));
    }

    public function test_edit_form_drops_references_to_deleted_media(): void
    {
        FieldGroup::where('key', 'group_oferta')->update(['fields' => [
            ['type' => 'image', 'name' => 'okladka'],
            ['type' => 'gallery', 'name' => 'galeria'],
        ]]);
        $page = $this->page(['fields' => ['okladka' => 999, 'galeria' => [998, 999]]]);

        $this->actingAs($this->user)->get("/cms/content/page/{$page->id}/edit")
            ->assertInertia(fn (Assert $p) => $p->where('values.okladka', null)->where('values.galeria', []));
    }
}
