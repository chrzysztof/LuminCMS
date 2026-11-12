<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Media\Media;
use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SeoSettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        $seo = $this->settings->group('seo');
        $image = $seo['default_og_image_id'] ? Media::find($seo['default_og_image_id']) : null;

        return Inertia::render('site-settings/seo', [
            'seo' => $seo,
            'defaultImage' => $image?->asArray('medium'),
            'sitemapUrl' => url('/sitemap.xml'),
            'robotsUrl' => url('/robots.txt'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:300'],
            'title_template' => ['required', 'string', 'max:150', 'regex:/%title%/'],
            'separator' => ['required', 'string', 'max:5'],
            'default_og_image_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'twitter_handle' => ['nullable', 'string', 'max:50', 'regex:/^@?[A-Za-z0-9_]*$/'],
            'noindex_site' => ['boolean'],
        ], ['title_template.regex' => __('Szablon musi zawierać znacznik %title%.')]);

        $this->settings->save('seo', [
            ...$data,
            'tagline' => $data['tagline'] ?? '',
            'description' => $data['description'] ?? '',
            'twitter_handle' => $data['twitter_handle'] ?? '',
            'noindex_site' => (bool) ($data['noindex_site'] ?? false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawienia SEO zapisane.')]);

        return back();
    }
}
