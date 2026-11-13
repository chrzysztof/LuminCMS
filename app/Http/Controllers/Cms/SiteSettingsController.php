<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Analytics\GoogleAnalyticsReport;
use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Ustawienia witryny: analityka i piksele, media społecznościowe, własny kod. */
class SiteSettingsController extends Controller
{
    private const SOCIAL = ['facebook', 'instagram', 'x', 'youtube', 'linkedin', 'tiktok'];

    public function __construct(private readonly Settings $settings, private readonly GoogleAnalyticsReport $analytics) {}

    public function edit(): Response
    {
        $site = $this->settings->group('site');
        unset($site['ga_service_account']); // klucz nigdy nie wraca do przeglądarki

        return Inertia::render('site-settings/site', [
            'site' => $site,
            'ga' => ['connected' => $this->analytics->isConfigured(), 'email' => $this->analytics->clientEmail()],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'ga_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'gtm_id' => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
            'pixel_id' => ['nullable', 'string', 'regex:/^\d{5,20}$/'],
            'ga_property_id' => ['nullable', 'string', 'regex:/^\d{5,15}$/'],
            'ga_service_account' => ['nullable', 'string', 'max:10000'],
            'ga_disconnect' => ['boolean'],
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
            'ga_id.regex' => __('Identyfikator Google Analytics 4 ma postać G-XXXXXXXXXX.'),
            'gtm_id.regex' => __('Identyfikator Google Tag Manager ma postać GTM-XXXXXXX.'),
            'ga_property_id.regex' => __('ID właściwości GA4 to same cyfry (Administracja → Ustawienia właściwości).'),
            'pixel_id.regex' => __('Identyfikator Pixela Meta to same cyfry.'),
            'privacy_url.regex' => __('Podaj ścieżkę (np. /polityka-prywatnosci) albo pełny adres http(s).'),
            '*.regex' => __('Podaj pełny adres zaczynający się od http:// lub https://.'),
        ]);

        $secret = trim((string) ($data['ga_service_account'] ?? ''));
        $encrypted = null;

        if ($secret !== '') {
            $encrypted = GoogleAnalyticsReport::encryptCredentials($secret);

            if ($encrypted === null) {
                throw ValidationException::withMessages(['ga_service_account' => __('To nie jest poprawny plik JSON konta usługi Google (client_email i private_key).')]);
            }
        }

        unset($data['ga_service_account'], $data['ga_disconnect']);

        $clean = [];
        foreach ($data as $key => $value) {
            $clean[$key] = is_bool($value) ? $value : trim((string) $value);
        }

        if ($encrypted !== null) {
            $clean['ga_service_account'] = $encrypted;
        } elseif ($request->boolean('ga_disconnect')) {
            $clean['ga_service_account'] = '';
        }

        $this->settings->save('site', $clean + [
            'consent_required' => (bool) ($data['consent_required'] ?? false),
            'track_logged_in' => (bool) ($data['track_logged_in'] ?? false),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawienia witryny zapisane.')]);

        return back();
    }
}
