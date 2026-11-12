<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'),
            'title' => 'Kontakt', 'slug' => 'kontakt', 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);
    }

    private function save(array $override = []): void
    {
        $this->actingAs(User::factory()->create())->put('/cms/settings/site', $override + [
            'ga_id' => '', 'gtm_id' => '', 'pixel_id' => '', 'consent_required' => true, 'track_logged_in' => false,
            'privacy_url' => '', 'facebook' => '', 'custom_head' => '', 'custom_body_end' => '',
        ])->assertSessionDoesntHaveErrors();
        $this->app['auth']->forgetGuards();
    }

    public function test_requires_login_and_renders_form(): void
    {
        $this->get('/cms/settings/site')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/cms/settings/site')->assertOk();
    }

    public function test_validates_identifiers_and_urls(): void
    {
        $this->actingAs(User::factory()->create())->put('/cms/settings/site', [
            'ga_id' => 'UA-1', 'gtm_id' => 'x', 'pixel_id' => 'abc', 'privacy_url' => 'javascript:alert(1)', 'facebook' => 'nie-url', 'x' => '//evil.example',
        ])->assertSessionHasErrors(['ga_id', 'gtm_id', 'pixel_id', 'privacy_url', 'facebook', 'x']);
    }

    public function test_no_tracking_markup_without_ids(): void
    {
        $this->get('/kontakt')->assertOk()->assertDontSee('lm-config', false)->assertDontSee('lumin-consent.js', false)->assertDontSee('Ustawienia cookies');
    }

    public function test_config_and_cookie_link_rendered_for_visitors_only(): void
    {
        $this->save(['ga_id' => 'G-ABCD1234', 'gtm_id' => 'GTM-ABC123', 'pixel_id' => '123456789', 'privacy_url' => '/polityka']);

        $html = $this->get('/kontakt')->assertOk()->assertSee('lumin-consent.js', false)->assertSee('Ustawienia cookies')->getContent();
        preg_match('~<script type="application/json" id="lm-config">(.*?)</script>~s', $html, $m);
        $this->assertSame(['ga' => 'G-ABCD1234', 'gtm' => 'GTM-ABC123', 'pixel' => '123456789', 'consent' => true, 'privacy' => '/polityka'], json_decode($m[1], true));

        // Zalogowany użytkownik nie jest śledzony, dopóki nie włączy tego w ustawieniach.
        $this->actingAs(User::factory()->create())->get('/kontakt')->assertDontSee('lm-config', false);

        $this->save(['ga_id' => 'G-ABCD1234', 'track_logged_in' => true]);
        $this->actingAs(User::factory()->create())->get('/kontakt')->assertSee('lm-config', false);
    }

    public function test_preview_has_no_tracking_or_custom_code(): void
    {
        $this->save(['ga_id' => 'G-ABCD1234', 'custom_head' => '<meta name="x" content="y">', 'track_logged_in' => true]);
        $page = Content::firstWhere('slug', 'kontakt');

        $this->actingAs(User::factory()->create())->get("/cms/content/page/{$page->id}/preview")
            ->assertOk()->assertDontSee('lm-config', false)->assertDontSee('name="x"', false);
    }

    public function test_custom_code_is_output_raw_for_visitors(): void
    {
        $this->save(['custom_head' => '<meta name="verify" content="abc">', 'custom_body_end' => '<div id="widget"></div>']);

        $this->get('/kontakt')->assertSee('<meta name="verify" content="abc">', false)->assertSee('<div id="widget"></div>', false);
    }

    public function test_consent_link_hidden_when_consent_not_required(): void
    {
        $this->save(['ga_id' => 'G-ABCD1234', 'consent_required' => false]);

        $this->get('/kontakt')->assertSee('lm-config', false)->assertDontSee('Ustawienia cookies');
    }

    public function test_social_profiles_feed_json_ld_organization(): void
    {
        $this->save(['facebook' => 'https://facebook.com/lumin', 'x' => 'https://x.com/lumin']);

        $html = $this->get('/kontakt')->getContent();
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $org = collect(json_decode($m[1], true)['@graph'])->firstWhere('@type', 'Organization');

        $this->assertSame(['https://facebook.com/lumin', 'https://x.com/lumin'], $org['sameAs']);
        $this->assertSame('https://facebook.com/lumin', app(Settings::class)->get('site', 'facebook'));
    }
}
