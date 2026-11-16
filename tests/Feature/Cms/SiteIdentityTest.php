<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Media\Media;
use App\Cms\Settings\Settings;
use App\Cms\Shortcodes\Shortcodes;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CmsSeeder::class);

        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Start', 'slug' => 'home',
            'status' => 'published', 'published_at' => now()->subMinute(), 'html_cache' => '<p>Witaj</p>',
        ]);
    }

    private function image(string $name, string $mime = 'image/png'): Media
    {
        return Media::create(['disk' => 'public', 'path' => "x/{$name}", 'filename' => $name, 'mime' => $mime, 'size' => 1, 'width' => 400, 'height' => 100]);
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['site_name' => 'Firma', 'title_template' => '%title% %sep% %site%', 'separator' => '–'];
    }

    public function test_without_logo_and_favicon_the_site_shows_its_name(): void
    {
        app(Settings::class)->save('seo', ['site_name' => 'Firma'] + app(Settings::class)->group('seo'));

        $this->get('/')->assertOk()
            ->assertSee('lm-site-header__brand', false)->assertSee('>Firma<', false)
            ->assertDontSee('lm-site-logo', false)->assertDontSee('rel="apple-touch-icon"', false);
    }

    public function test_settings_save_logo_and_favicon_and_theme_renders_them(): void
    {
        $logo = $this->image('logo.png');
        $favicon = $this->image('fav.png');

        $this->actingAs(User::factory()->create())
            ->put('/cms/settings/seo', $this->payload(['logo_id' => $logo->id, 'favicon_id' => $favicon->id]))->assertRedirect();

        $this->assertSame($logo->id, app(Settings::class)->get('seo', 'logo_id'));

        auth()->logout();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<img class="lm-site-logo"', $html);
        $this->assertStringContainsString('alt="Firma"', $html);
        $this->assertStringContainsString('rel="icon" href="'.$favicon->originalUrl().'" type="image/png"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('"logo":{"@type":"ImageObject"', $html);
    }

    public function test_non_image_media_is_rejected(): void
    {
        $pdf = $this->image('doc.pdf', 'application/pdf');

        $this->actingAs(User::factory()->create())
            ->put('/cms/settings/seo', $this->payload(['logo_id' => $pdf->id, 'favicon_id' => 9999]))
            ->assertSessionHasErrors(['logo_id', 'favicon_id']);
    }

    public function test_site_logo_shortcode_falls_back_to_site_name(): void
    {
        app(Settings::class)->save('seo', ['site_name' => 'Firma'] + app(Settings::class)->group('seo'));
        $this->assertSame('Firma', app(Shortcodes::class)->render('[site_logo]'));

        $logo = $this->image('logo.png');
        app(Settings::class)->save('seo', ['logo_id' => $logo->id] + app(Settings::class)->group('seo'));

        $this->assertStringContainsString('class="lm-site-logo"', app(Shortcodes::class)->render('Hej [site_logo]'));
    }
}
