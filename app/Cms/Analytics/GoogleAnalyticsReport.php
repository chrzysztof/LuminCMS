<?php

namespace App\Cms\Analytics;

use App\Cms\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Statystyki odwiedzin z Google Analytics 4 (Data API) dla pulpitu.
 * Opcjonalne: wymaga konta usługi Google z dostępem „Wyświetlający" do właściwości GA4.
 * Klucz konta usługi jest szyfrowany w bazie i nigdy nie wraca do przeglądarki.
 */
class GoogleAnalyticsReport
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CACHE_TTL = 1800;

    public function __construct(private readonly Settings $settings) {}

    public function isConfigured(): bool
    {
        return $this->propertyId() !== '' && $this->credentials() !== null;
    }

    /** Adres e-mail konta usługi (do wyświetlenia w ustawieniach). */
    public function clientEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    /** Szyfruje JSON konta usługi do zapisania w ustawieniach; null gdy JSON jest niepoprawny. */
    public static function encryptCredentials(string $json): ?string
    {
        $data = json_decode($json, true);

        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])
            || ! filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)
            || ! str_contains((string) $data['private_key'], 'PRIVATE KEY')) {
            return null;
        }

        return Crypt::encryptString(json_encode([
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
        ]));
    }

    /**
     * @return array{daily: list<array{date: string, users: int, sessions: int, views: int}>, totals: array{users: int, sessions: int, views: int}, pages: list<array{path: string, views: int}>}|array{error: string}
     */
    public function report(bool $fresh = false): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'not_configured'];
        }

        $key = 'cms.ga.report.'.md5($this->propertyId());

        if ($fresh) {
            Cache::forget($key);
        }

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $data = $this->fetch();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }

        Cache::put($key, $data, self::CACHE_TTL);

        return $data;
    }

    private function fetch(): array
    {
        $token = $this->accessToken();
        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/'.$this->propertyId().':runReport';
        $range = [['startDate' => '29daysAgo', 'endDate' => 'today']];

        $daily = Http::withToken($token)->timeout(15)->post($url, [
            'dateRanges' => $range,
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
            'metricAggregations' => ['TOTAL'],
        ]);

        $pages = Http::withToken($token)->timeout(15)->post($url, [
            'dateRanges' => $range,
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'screenPageViews']],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit' => 6,
        ]);

        foreach ([$daily, $pages] as $response) {
            if ($response->failed()) {
                throw new RuntimeException((string) ($response->json('error.message') ?: 'Google Analytics API error '.$response->status()));
            }
        }

        $metric = fn (array $row, int $i): int => (int) ($row['metricValues'][$i]['value'] ?? 0);

        $byDate = [];
        foreach ($daily->json('rows', []) as $row) {
            $d = (string) ($row['dimensionValues'][0]['value'] ?? '');
            $byDate[$d] = ['users' => $metric($row, 0), 'sessions' => $metric($row, 1), 'views' => $metric($row, 2)];
        }

        // Dni bez ruchu nie występują w odpowiedzi – uzupełniamy zerami, żeby wykres miał ciągłą oś.
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $series[] = ['date' => $day->toDateString()] + ($byDate[$day->format('Ymd')] ?? ['users' => 0, 'sessions' => 0, 'views' => 0]);
        }

        $total = $daily->json('totals.0', []);

        return [
            'daily' => $series,
            'totals' => ['users' => $metric($total, 0), 'sessions' => $metric($total, 1), 'views' => $metric($total, 2)],
            'pages' => array_map(fn ($row) => [
                'path' => mb_substr((string) ($row['dimensionValues'][0]['value'] ?? '/'), 0, 120),
                'views' => $metric($row, 0),
            ], $pages->json('rows', [])),
        ];
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'cms.ga.token.'.md5((string) $credentials['client_email']);

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $input = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $key = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Invalid service account private key.');
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $input.'.'.$b64($signature),
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException((string) ($response->json('error_description') ?: 'Google authorization failed.'));
        }

        Cache::put($cacheKey, $response->json('access_token'), 3000);

        return $response->json('access_token');
    }

    private function propertyId(): string
    {
        return (string) $this->settings->get('site', 'ga_property_id', '');
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $encrypted = (string) $this->settings->get('site', 'ga_service_account', '');

        if ($encrypted === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($encrypted), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($data) && isset($data['client_email'], $data['private_key']) ? $data : null;
    }
}
