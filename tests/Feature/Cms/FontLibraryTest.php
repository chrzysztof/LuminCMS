<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fonts\FontException;
use App\Cms\Fonts\FontLibrary;
use App\Cms\Themes\Customizer;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FontLibraryTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = <<<'CSS'
/* latin-ext */
@font-face {
  font-family: 'Poppins';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(https://fonts.gstatic.com/s/poppins/v21/ext400.woff2) format('woff2');
  unicode-range: U+0100-02BA, U+1E00-1EFF;
}
/* latin */
@font-face {
  font-family: 'Poppins';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url(https://fonts.gstatic.com/s/poppins/v21/lat400.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
/* cyrillic */
@font-face {
  font-family: 'Poppins';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/poppins/v21/cyr400.woff2) format('woff2');
  unicode-range: U+0400-045F;
}
CSS;

    private function fakeGoogle(): void
    {
        Storage::fake('public');
        Http::fake([
            'fonts.googleapis.com/*' => Http::response(self::SHEET),
            'fonts.gstatic.com/*' => Http::response('wOF2'.str_repeat('x', 100)),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrator']);
    }

    public function test_import_downloads_files_locally_and_keeps_only_needed_subsets(): void
    {
        $this->fakeGoogle();

        $font = app(FontLibrary::class)->import('Poppins', [400]);

        $this->assertSame('poppins', $font['slug']);
        $this->assertStringContainsString("src:url('/storage/fonts/poppins/", $font['css']);
        $this->assertStringNotContainsString('fonts.gstatic.com', $font['css']);
        $this->assertSame(2, substr_count($font['css'], '@font-face'));
        $this->assertCount(2, Storage::disk('public')->files('fonts/poppins'));
        $this->assertStringContainsString('unicode-range:U+0100-02BA', $font['css']);
    }

    public function test_invalid_family_names_are_rejected(): void
    {
        $this->fakeGoogle();

        $this->expectException(FontException::class);
        app(FontLibrary::class)->import("Poppins');}body{x:y", [400]);
    }

    public function test_imported_font_becomes_a_theme_option_and_is_printed_on_the_site(): void
    {
        $this->fakeGoogle();
        $font = app(FontLibrary::class)->import('Poppins', [400]);

        $customizer = app(Customizer::class);
        $token = collect($customizer->tokens())->firstWhere('key', 'font');
        $this->assertContains($font['stack'], array_column($token['options'], 'value'));

        $customizer->save(['font' => $font['stack']]);
        $this->seed(CmsSeeder::class);
        Content::create(['content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Strona', 'slug' => 'strona', 'status' => 'published', 'published_at' => now(), 'html_cache' => '<p>x</p>']);
        $this->get('/strona')->assertOk()->assertSee('@font-face{font-family:\'Poppins\'', false)->assertSee('--lm-font', false);
    }

    public function test_admin_can_import_and_remove_through_the_panel(): void
    {
        $this->fakeGoogle();
        $this->actingAs($this->admin());

        $this->post('/cms/fonts', ['family' => 'Poppins', 'weights' => [400]])->assertRedirect();
        $this->assertCount(1, app(FontLibrary::class)->all());

        $this->delete('/cms/fonts/poppins')->assertRedirect();
        $this->assertSame([], app(FontLibrary::class)->all());
        $this->assertSame([], Storage::disk('public')->files('fonts/poppins'));
    }

    public function test_missing_font_reports_an_error(): void
    {
        Storage::fake('public');
        Http::fake(['fonts.googleapis.com/*' => Http::response('', 400)]);
        $this->actingAs($this->admin());

        $this->post('/cms/fonts', ['family' => 'Nie Ma Takiej'])->assertSessionHasErrors('family');
    }

    public function test_italic_import_requests_ital_axis_and_keeps_style(): void
    {
        Storage::fake('public');
        Http::fake([
            'fonts.googleapis.com/*' => Http::response(str_replace('font-style: normal;', 'font-style: italic;', self::SHEET)),
            'fonts.gstatic.com/*' => Http::response('wOF2'.str_repeat('x', 100)),
        ]);

        $font = app(FontLibrary::class)->import('Poppins', [400], true);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'ital,wght@0,400;1,400'));
        $this->assertTrue($font['italic']);
        $this->assertStringContainsString('font-style:italic;font-weight:400', $font['css']);
    }

    public function test_heading_font_is_a_separate_theme_option(): void
    {
        $this->fakeGoogle();
        $font = app(FontLibrary::class)->import('Poppins', [400]);

        $customizer = app(Customizer::class);
        $token = collect($customizer->tokens())->firstWhere('key', 'heading_font');
        $this->assertContains($font['stack'], array_column($token['options'], 'value'));

        $customizer->save(['heading_font' => $font['stack']]);
        $this->assertStringContainsString('--lm-heading-font:', $customizer->css());
    }
}
