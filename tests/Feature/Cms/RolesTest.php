<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Media\Media;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function as(string $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    private function type(string $key = 'post'): ContentType
    {
        return ContentType::where('key', $key)->firstOrFail();
    }

    private function content(User $author, string $status = 'draft', string $key = 'post'): Content
    {
        return Content::create([
            'content_type_id' => $this->type($key)->id, 'author_id' => $author->id,
            'title' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'status' => $status,
            'published_at' => $status === 'published' ? now() : null, 'fields' => [],
        ]);
    }

    private function payload(array $extra = []): array
    {
        return ['title' => 'Nowy wpis', 'status' => 'draft', 'fields' => [], ...$extra];
    }

    public function test_role_matrix_follows_wordpress(): void
    {
        $this->assertTrue(RoleRegistry::grants('subscriber', 'read'));
        $this->assertFalse(RoleRegistry::grants('subscriber', 'edit_posts'));
        $this->assertTrue(RoleRegistry::grants('contributor', 'edit_posts'));
        $this->assertFalse(RoleRegistry::grants('contributor', 'publish_posts'));
        $this->assertFalse(RoleRegistry::grants('contributor', 'upload_files'));
        $this->assertTrue(RoleRegistry::grants('author', 'publish_posts'));
        $this->assertTrue(RoleRegistry::grants('author', 'upload_files'));
        $this->assertFalse(RoleRegistry::grants('author', 'edit_others_posts'));
        $this->assertFalse(RoleRegistry::grants('author', 'edit_pages'));
        $this->assertTrue(RoleRegistry::grants('editor', 'edit_others_pages'));
        $this->assertFalse(RoleRegistry::grants('editor', 'manage_options'));
        $this->assertTrue(RoleRegistry::grants('administrator', 'manage_options'));
        $this->assertTrue(RoleRegistry::grants('administrator', 'promote_users'));
    }

    public function test_gate_exposes_capabilities(): void
    {
        $author = User::factory()->role('author')->create();
        $this->assertTrue($author->can('upload_files'));
        $this->assertFalse($author->can('manage_options'));
    }

    public function test_contributor_submissions_become_pending_and_cannot_edit_published(): void
    {
        $user = $this->as('contributor');

        $this->post(route('cms.content.store', 'post'), $this->payload(['status' => 'published']))->assertRedirect();
        $own = Content::where('author_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $own->status);

        // Własne, opublikowane przez kogoś innego → brak edit_published_posts.
        $published = $this->content($user, 'published');
        $this->get(route('cms.content.edit', ['post', $published]))->assertForbidden();
        $this->put(route('cms.content.update', ['post', $published]), $this->payload())->assertForbidden();
        $this->delete(route('cms.content.destroy', ['post', $published]))->assertForbidden();

        // Własny niepublikowany wpis można edytować i usunąć.
        $this->put(route('cms.content.update', ['post', $own]), $this->payload(['title' => 'Zmiana']))->assertRedirect();
        $this->delete(route('cms.content.destroy', ['post', $own]))->assertRedirect();
    }

    public function test_contributor_cannot_touch_pages_or_others_posts(): void
    {
        $this->as('contributor');
        $other = User::factory()->role('author')->create();
        $theirs = $this->content($other);

        $this->get(route('cms.content.index', 'page'))->assertForbidden();
        $this->post(route('cms.content.store', 'page'), $this->payload())->assertForbidden();
        $this->get(route('cms.content.edit', ['post', $theirs]))->assertForbidden();
        $this->delete(route('cms.content.destroy', ['post', $theirs]))->assertForbidden();
    }

    public function test_author_publishes_own_but_not_others(): void
    {
        $author = $this->as('author');

        $this->post(route('cms.content.store', 'post'), $this->payload(['status' => 'published']))->assertRedirect();
        $this->assertSame('published', Content::where('author_id', $author->id)->firstOrFail()->status);

        $theirs = $this->content(User::factory()->role('editor')->create());
        $this->get(route('cms.content.edit', ['post', $theirs]))->assertForbidden();
    }

    public function test_list_is_scoped_to_own_content_without_edit_others(): void
    {
        $author = $this->as('author');
        $mine = $this->content($author);
        $theirs = $this->content(User::factory()->role('author')->create());

        $titles = collect($this->get(route('cms.content.index', 'post'))->viewData('page')['props']['items']['data'])->pluck('id');

        $this->assertTrue($titles->contains($mine->id));
        $this->assertFalse($titles->contains($theirs->id));
    }

    public function test_editor_manages_everything_in_content(): void
    {
        $this->as('editor');
        $other = $this->content(User::factory()->role('author')->create(), 'published');
        $page = $this->content(User::factory()->create(), 'draft', 'page');

        $this->get(route('cms.content.edit', ['post', $other]))->assertOk();
        $this->get(route('cms.content.edit', ['page', $page]))->assertOk();
        $this->delete(route('cms.content.destroy', ['post', $other]))->assertRedirect();
    }

    public function test_admin_only_areas_are_forbidden_for_editor(): void
    {
        $this->as('editor');

        foreach (['cms.fields.index', 'cms.redirects.index', 'cms.settings.seo', 'cms.settings.site', 'cms.media-sizes.index', 'cms.users.index', 'cms.users.roles'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_subscriber_has_no_cms_access_but_dashboard_works(): void
    {
        $this->as('subscriber');

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('cms.content.index', 'post'))->assertForbidden();
        $this->get(route('cms.library'))->assertForbidden();
    }

    public function test_media_permissions(): void
    {
        $this->as('contributor');
        $this->getJson(route('cms.media.index'))->assertForbidden();

        $owner = User::factory()->role('author')->create();
        $media = Media::create([
            'disk' => 'public', 'path' => 'x/a.png', 'filename' => 'a.png', 'mime' => 'image/png',
            'size' => 1, 'width' => 1, 'height' => 1, 'uploaded_by' => $owner->id,
        ]);

        $this->as('author');
        $this->patchJson(route('cms.media.update', $media), ['alt' => 'x'])->assertForbidden();
        $this->deleteJson(route('cms.media.destroy', $media))->assertForbidden();

        $this->actingAs($owner);
        $this->patchJson(route('cms.media.update', $media), ['alt' => 'x'])->assertOk();

        $this->as('editor');
        $this->patchJson(route('cms.media.update', $media), ['alt' => 'y'])->assertOk();
    }

    public function test_users_crud_by_administrator(): void
    {
        $this->as('administrator');

        $this->get(route('cms.users.index'))->assertOk();

        $this->post(route('cms.users.store'), [
            'name' => 'Ola', 'email' => 'ola@example.com', 'password' => 'password123', 'role' => 'editor',
        ])->assertRedirect();

        $user = User::where('email', 'ola@example.com')->firstOrFail();
        $this->assertSame('editor', $user->role);
        $this->assertNotNull($user->email_verified_at);

        $this->put(route('cms.users.update', $user), [
            'name' => 'Ola K', 'email' => 'ola@example.com', 'password' => '', 'role' => 'author',
        ])->assertRedirect();
        $this->assertSame('author', $user->refresh()->role);
        $this->assertSame('Ola K', $user->name);

        $this->get(route('cms.users.roles'))->assertOk();
        $this->put(route('cms.users.settings'), ['default_role' => 'contributor', 'registration_enabled' => true])->assertRedirect();
        $this->assertSame('contributor', app(Settings::class)->get('users', 'default_role'));
    }

    public function test_invalid_role_rejected(): void
    {
        $this->as('administrator');

        $this->post(route('cms.users.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'role' => 'superman',
        ])->assertSessionHasErrors('role');
    }

    public function test_cannot_demote_or_delete_last_administrator_or_self(): void
    {
        $admin = $this->as('administrator');

        $this->put(route('cms.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'editor',
        ])->assertSessionHasErrors('role');
        $this->assertSame('administrator', $admin->refresh()->role);

        $this->delete(route('cms.users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertNotNull($admin->fresh());

        // Z drugim administratorem pierwszy może zostać zdegradowany.
        User::factory()->role('administrator')->create();
        $this->put(route('cms.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'editor',
        ])->assertRedirect();
    }

    public function test_delete_user_reassigns_content(): void
    {
        $this->as('administrator');
        $author = User::factory()->role('author')->create();
        $heir = User::factory()->role('editor')->create();
        $content = $this->content($author);

        $this->delete(route('cms.users.destroy', $author), ['reassign_to' => $heir->id])->assertRedirect(route('cms.users.index'));

        $this->assertNull(User::find($author->id));
        $this->assertSame($heir->id, $content->refresh()->author_id);
    }

    public function test_editor_cannot_manage_users(): void
    {
        $this->as('editor');
        $target = User::factory()->create();

        $this->post(route('cms.users.store'), ['name' => 'a', 'email' => 'a@a.pl', 'password' => 'password123', 'role' => 'author'])->assertForbidden();
        $this->put(route('cms.users.update', $target), ['name' => 'a', 'email' => 'a@a.pl', 'role' => 'author'])->assertForbidden();
        $this->delete(route('cms.users.destroy', $target))->assertForbidden();
    }

    public function test_registration_first_user_admin_then_closed_until_enabled(): void
    {
        $payload = ['name' => 'Pierwszy', 'email' => 'p@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'];

        $this->post(route('register.store'), $payload)->assertRedirect();
        $this->assertSame('administrator', User::where('email', 'p@example.com')->firstOrFail()->role);

        $this->app['auth']->forgetGuards();
        $this->post('/logout');

        $this->get('/register')->assertNotFound();

        app(Settings::class)->save('users', ['default_role' => 'contributor', 'registration_enabled' => true]);

        $this->post(route('register.store'), ['name' => 'Drugi', 'email' => 'd@example.com'] + array_slice($payload, 2))->assertRedirect();
        $this->assertSame('contributor', User::where('email', 'd@example.com')->firstOrFail()->role);
    }

    public function test_dashboard_stats_are_scoped(): void
    {
        $author = $this->as('author');
        $this->content($author, 'published');
        $this->content(User::factory()->role('editor')->create(), 'published');

        $this->assertSame(1, $this->get(route('dashboard'))->viewData('page')['props']['stats']['published']);
    }

    public function test_seo_roles_for_external_agencies(): void
    {
        $this->assertTrue(RoleRegistry::grants('seo_manager', 'manage_seo'));
        $this->assertTrue(RoleRegistry::grants('seo_manager', 'publish_pages'));
        $this->assertFalse(RoleRegistry::grants('seo_manager', 'manage_options'));
        $this->assertFalse(RoleRegistry::grants('seo_manager', 'delete_pages'));
        $this->assertFalse(RoleRegistry::grants('seo_manager', 'list_users'));
        $this->assertTrue(RoleRegistry::grants('seo_specialist', 'edit_others_pages'));
        $this->assertFalse(RoleRegistry::grants('seo_specialist', 'publish_pages'));
        $this->assertFalse(RoleRegistry::grants('seo_specialist', 'manage_seo'));
        $this->assertTrue(RoleRegistry::grants('administrator', 'manage_seo'));
        $this->assertFalse(RoleRegistry::grants('editor', 'manage_seo'));
    }

    public function test_seo_manager_access_areas(): void
    {
        $this->as('seo_manager');

        $this->get(route('cms.settings.seo'))->assertOk();
        $this->get(route('cms.redirects.index'))->assertOk();
        $this->get(route('cms.settings.site'))->assertForbidden();
        $this->get(route('cms.fields.index'))->assertForbidden();
        $this->get(route('cms.users.index'))->assertForbidden();
    }

    public function test_seo_specialist_edits_published_content_without_changing_status(): void
    {
        $this->as('seo_specialist');
        $page = $this->content(User::factory()->create(), 'published', 'page');

        $this->get(route('cms.settings.seo'))->assertForbidden();
        $this->delete(route('cms.content.destroy', ['page', $page]))->assertForbidden();

        $this->put(route('cms.content.update', ['page', $page]), $this->payload([
            'title' => $page->title, 'status' => 'draft', 'seo' => ['title' => 'Nowy tytuł SEO'],
        ]))->assertRedirect();

        $page->refresh();
        $this->assertSame('published', $page->status);
        $this->assertSame('Nowy tytuł SEO', $page->seo['title']);
    }
}
