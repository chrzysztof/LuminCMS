<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\ContentRevision;
use App\Cms\Builder\SavedBlock;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuilderLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function page(): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Home', 'slug' => 'home', 'status' => 'draft',
        ]);
    }

    private function project(string $text): array
    {
        return ['pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
            ['tagName' => 'h1', 'type' => 'text', 'components' => [['type' => 'textnode', 'content' => $text]]],
        ]]]]]]];
    }

    public function test_saved_blocks_can_be_created_listed_in_builder_and_deleted(): void
    {
        $user = User::factory()->create();
        $page = $this->page();

        $this->actingAs($user)->postJson('/cms/saved-blocks', ['name' => 'Hero A', 'html' => '<section class="lm-section"><h1>A</h1></section>'])
            ->assertCreated()->assertJsonPath('name', 'Hero A');

        $this->actingAs($user)->get("/cms/content/page/{$page->id}/builder")
            ->assertInertia(fn (Assert $p) => $p->has('savedBlocks', 1)->where('savedBlocks.0.name', 'Hero A'));

        $this->postJson('/cms/saved-blocks', ['name' => '', 'html' => ''])->assertUnprocessable();

        $this->deleteJson('/cms/saved-blocks/'.SavedBlock::first()->id)->assertOk();
        $this->assertSame(0, SavedBlock::count());
    }

    public function test_only_author_or_admin_can_delete_a_saved_block(): void
    {
        $author = User::factory()->role('author')->create();
        $other = User::factory()->role('author')->create();
        $admin = User::factory()->create();
        $block = SavedBlock::create(['name' => 'X', 'html' => '<p>x</p>', 'user_id' => $author->id]);

        $this->actingAs($other)->deleteJson("/cms/saved-blocks/{$block->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/cms/saved-blocks/{$block->id}")->assertOk();
    }

    public function test_subscriber_cannot_use_saved_blocks(): void
    {
        $this->actingAs(User::factory()->role('subscriber')->create())
            ->postJson('/cms/saved-blocks', ['name' => 'X', 'html' => '<p>x</p>'])->assertForbidden();
    }

    public function test_save_records_revisions_and_skips_identical_projects(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create());

        $this->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project('Jeden')])->assertOk();
        $this->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project('Jeden')])->assertOk();
        $this->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project('Dwa'), 'status' => 'published'])->assertOk();

        $this->assertSame(2, ContentRevision::where('content_id', $page->id)->count());
        $this->assertSame('publish', ContentRevision::latest('id')->first()->kind);

        $list = $this->getJson("/cms/content/page/{$page->id}/builder/revisions")->assertOk()->json();
        $this->assertCount(2, $list);

        $this->getJson("/cms/content/page/{$page->id}/builder/revisions/{$list[1]['id']}")
            ->assertOk()->assertJsonPath('project', $this->project('Jeden'));
    }

    public function test_autosave_stores_a_revision_without_touching_the_live_page(): void
    {
        $page = $this->page();
        $user = User::factory()->create();

        $this->actingAs($user)->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project('Zapisane')])->assertOk();
        $this->putJson("/cms/content/page/{$page->id}/builder/autosave", ['project' => $this->project('Robocze')])
            ->assertOk()->assertJsonPath('stored', true);

        $this->assertSame($this->project('Zapisane'), $page->refresh()->builder_data);
        $this->assertSame('autosave', ContentRevision::latest('id')->first()->kind);

        // Po ręcznym zapisie tej samej wersji autozapis przestaje być "do odzyskania".
        $this->get("/cms/content/page/{$page->id}/builder")
            ->assertInertia(fn (Assert $p) => $p->where('recovery.id', ContentRevision::latest('id')->first()->id));

        $this->putJson("/cms/content/page/{$page->id}/builder", ['project' => $this->project('Robocze')])->assertOk();
        // Identyczny projekt awansuje autozapis do zapisu zamiast tworzyć duplikat.
        $this->assertSame(2, ContentRevision::count());
        $this->assertSame(0, ContentRevision::where('kind', 'autosave')->count());
        $this->get("/cms/content/page/{$page->id}/builder")->assertInertia(fn (Assert $p) => $p->where('recovery', null));
    }

    public function test_old_autosaves_are_pruned(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create());

        foreach (range(1, 20) as $i) {
            $this->putJson("/cms/content/page/{$page->id}/builder/autosave", ['project' => $this->project("v{$i}")])->assertOk();
        }

        $this->assertSame(15, ContentRevision::where('kind', 'autosave')->count());
    }

    public function test_revision_of_another_content_is_not_reachable(): void
    {
        $a = $this->page();
        $b = Content::create(['content_type_id' => $a->content_type_id, 'title' => 'B', 'slug' => 'b', 'status' => 'draft']);
        $this->actingAs(User::factory()->create());
        $this->putJson("/cms/content/page/{$a->id}/builder", ['project' => $this->project('A')])->assertOk();

        $this->getJson("/cms/content/page/{$b->id}/builder/revisions/".ContentRevision::first()->id)->assertNotFound();
    }
}
