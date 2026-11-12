<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ustawienia witryny: analityka i piksele, media społecznościowe, własny kod. */
class SiteSettingsController extends Controller
{
    private const SOCIAL = ['facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok'];

    public function __construct(private readonly Settings $settings) {}

    public function edit(): Response
    {
        return Inertia::render('site-settings/site', ['site' => $this->settings->group('site')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'ga_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
            'pixel_id' => ['nullable', 'string', 'regex:/^\d{5,20}$/'],
            'consent_required' => ['boolean'],
            'track_logged_in' => ['boolean'],
            'privacy_url' => ['nullable', 'string', 'max:500', 'regex:~^(/(?!/)|https?://)\S*$~i'],
            'custom_head' => ['nullable', 'string', 'max:20000'],
            'custom_body_end' => ['nullable', 'string', 'max:20000'],
        ];

        foreach (self::SOCIAL as $key) {
            $rules[$key] = ['nullable', 'string', 'max:255', 'regex:~^https?://\S+$~i'];
        }

        $data = $request->validate($rules, [
            'ga_id.regex' => 'Identyfikator Google Analytics 4 ma postać G-XXXXXXXXXX.',
            'gtm_id.regex' => 'Identyfikator Google Tag Manager ma postać GTM-XXXXXXX.',
            'pixel_id.regex' => 'Identyfikator Pixela Meta to same cyfry.',
            'privacy_url.regex' => 'Podaj ścieżkę (np. /polityka-prywatnosci) albo pełny adres http(s).',
            '*.regex' => 'Podaj pełny adres zaczynający się od http:// lub https://.',
        ]);

        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = is_bool($value) ? $value : trim((string) $value);
        }

        $this->settings->save('site', $clean + [
            'consent_required' => (bool) ($data['consent_required'] ?? false),
            'track_logged_in' => (bool) ($data['track_logged_in'] ?? false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ustawienia witryny zapisane.']);

        return back();
    }
}
