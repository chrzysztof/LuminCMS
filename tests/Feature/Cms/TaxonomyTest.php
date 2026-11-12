<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->actingAs(User::factory()->role(RoleRegistry::ADMINISTRATOR)->create());
    }

    private function tax(string $key = 'category'): Taxonomy
    {
        return Taxonomy::where('key', $key)->firstOrFail();
    }

    private function term(string $name, ?Term $parent = null, string $key = 'category'): Term
    {
        return Term::create(['taxonomy_id' => $this->tax($key)->id, 'name' => $name, 'slug' => str($name)->slug(), 'parent_id' => $parent?->id]);
    }

    public function test_term_is_created_with_unique_slug(): void
    {
        $this->post('/cms/taxonomies/category/terms', ['name' => 'Nowości'])->assertRedirect();
        $this->post('/cms/taxonomies/category/terms', ['name' => 'Nowości'])->assertRedirect();

        $this->assertSame(['nowosci', 'nowosci-2'], Term::orderBy('id')->pluck('slug')->all());
    }

    public function test_index_lists_terms_in_tree_order(): void
    {
        $a = $this->term('Alfa');
        $this->term('Dziecko', $a);
        $this->term('Beta');

        $terms = $this->tax()->flatTerms();

        $this->assertSame(['Alfa', 'Dziecko', 'Beta'], array_column($terms, 'name'));
        $this->assertSame([0, 1, 0], array_column($terms, 'depth'));
    }

    public function test_parent_loop_is_rejected(): void
    {
        $a = $this->term('A');
        $b = $this->term('B', $a);

        $this->put("/cms/taxonomies/category/terms/{$a->id}", ['name' => 'A', 'parent_id' => $b->id])
            ->assertSessionHasErrors('parent_id');
        $this->put("/cms/taxonomies/category/terms/{$a->id}", ['name' => 'A', 'parent_id' => $a->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_flat_taxonomy_ignores_parent(): void
    {
        $tag = $this->term('Jeden', null, 'tag');

        $this->post('/cms/taxonomies/tag/terms', ['name' => 'Dwa', 'parent_id' => $tag->id])->assertRedirect();

        $this->assertNull(Term::where('name', 'Dwa')->first()->parent_id);
    }

    public function test_deleting_term_moves_children_up_and_detaches_content(): void
    {
        $a = $this->term('A');
        $b = $this->term('B', $a);
        $c = $this->term('C', $b);
        $post = Content::create(['content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => 'P', 'slug' => 'p', 'status' => 'draft', 'fields' => []]);
        $post->terms()->attach($b);

        $this->delete("/cms/taxonomies/category/terms/{$b->id}")->assertRedirect();

        $this->assertSame($a->id, $c->refresh()->parent_id);
        $this->assertSame(0, $post->terms()->count());
    }

    public function test_term_of_other_taxonomy_cannot_be_edited_through_wrong_url(): void
    {
        $tag = $this->term('T', null, 'tag');

        $this->put("/cms/taxonomies/category/terms/{$tag->id}", ['name' => 'X'])->assertNotFound();
    }

    public function test_custom_taxonomy_lifecycle_and_system_protection(): void
    {
        $this->post('/cms/taxonomies', [
            'key' => 'temat', 'label' => 'Temat', 'label_plural' => 'Tematy', 'hierarchical' => true, 'content_types' => ['page'],
        ])->assertRedirect();

        $custom = $this->tax('temat');
        $this->assertTrue($custom->hierarchical);
        $this->assertSame(['page'], $custom->contentTypes()->pluck('key')->all());

        $this->post('/cms/taxonomies', ['key' => 'temat', 'label' => 'x', 'label_plural' => 'x'])->assertSessionHasErrors('key');

        $this->delete('/cms/taxonomies/category')->assertForbidden();
        $this->delete('/cms/taxonomies/temat')->assertRedirect();
        $this->assertNull(Taxonomy::where('key', 'temat')->first());
    }

    public function test_content_form_assigns_terms(): void
    {
        $category = $this->term('Kat');
        $custom = Taxonomy::create(['key' => 'temat', 'label' => 'T', 'label_plural' => 'T', 'hierarchical' => false]);
        $foreign = Term::create(['taxonomy_id' => $custom->id, 'name' => 'Obcy', 'slug' => 'obcy']);

        $this->post('/cms/content/post', ['title' => 'Z kategorią', 'status' => 'draft', 'fields' => [], 'term_ids' => [$category->id]])->assertRedirect();
        $post = Content::where('title', 'Z kategorią')->firstOrFail();
        $this->assertSame([$category->id], $post->terms()->pluck('terms.id')->all());

        // Termin taksonomii niepodpiętej pod typ treści jest odrzucany.
        $this->put("/cms/content/post/{$post->id}", ['title' => 'Z kategorią', 'status' => 'draft', 'fields' => [], 'term_ids' => [$foreign->id]])
            ->assertSessionHasErrors('term_ids.0');

        // Brak term_ids nie rusza przypisań; pusta lista je czyści.
        $this->put("/cms/content/post/{$post->id}", ['title' => 'Z kategorią', 'status' => 'draft', 'fields' => []])->assertRedirect();
        $this->assertSame(1, $post->terms()->count());
        $this->put("/cms/content/post/{$post->id}", ['title' => 'Z kategorią', 'status' => 'draft', 'fields' => [], 'term_ids' => []])->assertRedirect();
        $this->assertSame(0, $post->terms()->count());
    }

    public function test_only_users_with_manage_categories_open_the_panel(): void
    {
        $this->actingAs(User::factory()->role(RoleRegistry::CONTRIBUTOR)->create());
        $this->get('/cms/taxonomies')->assertForbidden();

        $this->actingAs(User::factory()->role(RoleRegistry::EDITOR)->create());
        $this->post('/cms/taxonomies', ['key' => 'x', 'label' => 'x', 'label_plural' => 'x'])->assertForbidden();
        $this->post('/cms/taxonomies/category/terms', ['name' => 'Redaktor dodał'])->assertRedirect();
    }

    public function test_panel_page_keeps_the_shared_sidebar_content_types(): void
    {
        $this->get('/cms/taxonomies')->assertOk()->assertInertia(fn ($page) => $page
            ->component('taxonomies/index')
            ->has('typeOptions')
            ->where('contentTypes.0.label_plural', fn ($v) => $v !== null));
    }
}
