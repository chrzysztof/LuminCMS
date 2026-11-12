<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
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
