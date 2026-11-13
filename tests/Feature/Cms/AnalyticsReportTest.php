<?php

namespace Tests\Feature\Cms;

use App\Cms\Analytics\GoogleAnalyticsReport;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    private function credentialsJson(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);

        return json_encode(['type' => 'service_account', 'client_email' => 'lumin@proj.iam.gserviceaccount.com', 'private_key' => $pem]);
    }

    private function configure(): void
    {
        $this->seed(CmsSeeder::class);
        Cache::flush();
        app(Settings::class)->save('site', [
            'ga_property_id' => '123456789',
            'ga_service_account' => GoogleAnalyticsReport::encryptCredentials($this->credentialsJson()),
        ]);
    }

    public function test_service_account_key_is_encrypted_and_never_sent_to_the_browser(): void
    {
        $this->seed(CmsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->put('/cms/settings/site', [
            'ga_property_id' => '123456789', 'ga_service_account' => $this->credentialsJson(),
            'consent_required' => true, 'track_logged_in' => false,
        ])->assertSessionDoesntHaveErrors();

        $stored = app(Settings::class)->get('site', 'ga_service_account');
        $this->assertNotEmpty($stored);
        $this->assertStringNotContainsString('PRIVATE KEY', $stored);

        $this->actingAs($user)->get('/cms/settings/site')
            ->assertInertia(fn ($page) => $page
                ->where('ga.connected', true)
                ->where('ga.email', 'lumin@proj.iam.gserviceaccount.com')
                ->missing('site.ga_service_account'));

        // Zapis bez nowego klucza go nie kasuje; „ga_disconnect" usuwa.
        $this->actingAs($user)->put('/cms/settings/site', ['ga_property_id' => '123456789', 'consent_required' => true]);
        $this->assertSame($stored, app(Settings::class)->get('site', 'ga_service_account'));

        $this->actingAs($user)->put('/cms/settings/site', ['ga_disconnect' => true, 'consent_required' => true]);
        $this->assertSame('', app(Settings::class)->get('site', 'ga_service_account'));
    }

    public function test_invalid_service_account_json_and_property_id_are_rejected(): void
    {
        $this->seed(CmsSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)->put('/cms/settings/site', ['ga_property_id' => 'abc', 'consent_required' => true])
            ->assertSessionHasErrors('ga_property_id');

        $this->actingAs($user)->put('/cms/settings/site', ['ga_property_id' => '12345', 'ga_service_account' => '{"foo":1}', 'consent_required' => true])
            ->assertSessionHasErrors('ga_service_account');
    }

    public function test_report_returns_filled_daily_series_totals_and_top_pages(): void
    {
        $this->configure();
        $today = now()->format('Ymd');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsdata.googleapis.com/*' => Http::sequence()
                ->push([
                    'rows' => [['dimensionValues' => [['value' => $today]], 'metricValues' => [['value' => '3'], ['value' => '4'], ['value' => '9']]]],
                    'totals' => [['metricValues' => [['value' => '30'], ['value' => '40'], ['value' => '90']]]],
                ])
                ->push(['rows' => [['dimensionValues' => [['value' => '/oferta']], 'metricValues' => [['value' => '70']]]]]),
        ]);

        $res = $this->actingAs(User::factory()->create())->getJson('/cms/analytics')->assertOk();

        $res->assertJsonCount(30, 'daily')
            ->assertJsonPath('daily.29.views', 9)
            ->assertJsonPath('daily.0.views', 0)
            ->assertJsonPath('totals', ['users' => 30, 'sessions' => 40, 'views' => 90])
            ->assertJsonPath('pages.0.path', '/oferta');

        // Drugie wywołanie korzysta z pamięci podręcznej.
        $this->getJson('/cms/analytics')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_api_errors_are_reported_and_unconfigured_returns_422(): void
    {
        $this->seed(CmsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/cms/analytics')->assertStatus(422)->assertJsonPath('error', 'not_configured');

        $this->configure();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => ['message' => 'Permission denied']], 403),
        ]);

        $this->actingAs($user)->getJson('/cms/analytics')->assertStatus(422)->assertJsonPath('error', 'Permission denied');
    }

    public function test_dashboard_flags_the_widget_only_when_configured(): void
    {
        $this->seed(CmsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('analytics', false));

        $this->configure();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p->where('analytics', true));
    }
}
