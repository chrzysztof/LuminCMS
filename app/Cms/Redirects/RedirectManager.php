<?php

namespace App\Cms\Redirects;

use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Przekierowania: normalizacja adresów, dopasowanie, unikanie pętli i łańcuchów,
 * automatyczne przekierowania po zmianie adresu treści oraz monitor błędów 404.
 */
class RedirectManager
{
    /** Adresy zajęte przez panel i system: nie można ich przekierować. */
    private const RESERVED = ['cms', 'build', 'storage', 'login', 'logout', 'register', 'dashboard', 'settings', 'up', 'sitemap.xml', 'robots.txt', 'two-factor-challenge'];

    private const LOG_LIMIT = 500;

    public function __construct(private readonly ContentResolver $paths) {}

    /** "/Stara/Strona/?a=1#x" → "/stara/strona"; adres z domeną tej witryny też jest sprowadzany do ścieżki. */
    public function normalize(string $input): string
    {
        $input = trim($input);

        if (preg_match('~^https?://([^/]+)(.*)$~i', $input, $m)) {
            if (strcasecmp($m[1], parse_url(url('/'), PHP_URL_HOST).(parse_url(url('/'), PHP_URL_PORT) ? ':'.parse_url(url('/'), PHP_URL_PORT) : '')) !== 0) {
                throw new InvalidArgumentException('Adres źródłowy musi należeć do tej witryny.');
            }
            $input = $m[2];
        }

        $path = parse_url('http://x/'.ltrim($input, '/'), PHP_URL_PATH) ?: '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return mb_strtolower($path === '' ? '/' : $path);
    }

    /** Waliduje źródło i cel. Zwraca [source, target]. @throws InvalidArgumentException */
    public function prepare(string $source, ?string $target, int $code, ?int $ignoreId = null): array
    {
        $source = $this->normalize($source);
        $first = explode('/', ltrim($source, '/'))[0];

        if ($source === '/') {
            throw new InvalidArgumentException('Strony głównej nie można przekierować.');
        }

        if (in_array($first, self::RESERVED, true)) {
            throw new InvalidArgumentException('Ten adres jest zarezerwowany dla panelu lub systemu.');
        }

        if (Redirect::where('source', $source)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            throw new InvalidArgumentException('Dla tego adresu istnieje już przekierowanie.');
        }

        if ($code === 410) {
            return [$source, null];
        }

        $target = trim((string) $target);

        if ($target === '') {
            throw new InvalidArgumentException('Podaj adres docelowy.');
        }

        if (preg_match('~^https?://~i', $target)) {
            if (! filter_var($target, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Nieprawidłowy adres docelowy.');
            }
        } elseif (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            $target = '/'.ltrim($target, '/');
        } else {
            throw new InvalidArgumentException('Adres docelowy: ścieżka zaczynająca się od „/” albo pełny adres http(s).');
        }

        if ($this->isInternal($target) && $this->leadsBackTo($target, $source, $ignoreId)) {
            throw new InvalidArgumentException('Takie przekierowanie utworzyłoby pętlę.');
        }

        return [$source, $target];
    }

    public function create(string $source, ?string $target, int $code = 301, array $extra = []): Redirect
    {
        [$source, $target] = $this->prepare($source, $target, $code);

        // Spłaszczamy łańcuchy: A→B, gdy B już przekierowuje do C, daje A→C.
        if ($target !== null && $this->isInternal($target) && ($next = $this->find($this->normalize($target)))) {
            $target = $next->target;
            $code = $next->status_code === 410 ? 410 : $code;
        }

        $redirect = Redirect::create(['source' => $source, 'target' => $target, 'status_code' => $code] + $extra);

        if ($redirect->target !== null && $this->isInternal($redirect->target)) {
            // Wszystko, co wskazywało na nowe źródło, wskazuje teraz od razu na jego cel.
            Redirect::where('target', $source)->where('id', '!=', $redirect->id)
                ->update(['target' => $redirect->target]);
        }

        return $redirect;
    }

    public function find(string $path): ?Redirect
    {
        return Redirect::where('source', $this->normalize($path))->where('active', true)->first();
    }

    /** Odpowiedź przekierowania dla żądania albo null. Parametry zapytania zachowujemy dla adresów wewnętrznych. */
    public function match(Request $request): ?array
    {
        $redirect = $this->find('/'.$request->path());

        if (! $redirect) {
            return null;
        }

        $redirect->forceFill(['hits' => $redirect->hits + 1, 'last_hit_at' => now()])->saveQuietly();

        if ($redirect->status_code === 410) {
            return ['code' => 410, 'target' => null];
        }

        $target = $redirect->target;

        if ($this->isInternal($target) && $request->getQueryString()) {
            $target .= (str_contains($target, '?') ? '&' : '?').$request->getQueryString();
        }

        return ['code' => $redirect->status_code, 'target' => $target];
    }

    public function isInternal(?string $target): bool
    {
        return $target !== null && str_starts_with($target, '/');
    }

    /** Czy podążanie za przekierowaniami od $target prowadzi do $source (pętla). */
    private function leadsBackTo(string $target, string $source, ?int $ignoreId): bool
    {
        $seen = [];
        $current = $this->normalize($target);

        for ($i = 0; $i < 20; $i++) {
            if ($current === $source) {
                return true;
            }

            if (isset($seen[$current])) {
                return false;
            }
            $seen[$current] = true;

            $next = Redirect::where('source', $current)->where('active', true)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->first();

            if (! $next || ! $this->isInternal($next->target)) {
                return false;
            }

            $current = $this->normalize($next->target);
        }

        return true;
    }

    // --- Automatyczne przekierowania po zmianie adresu treści -------------------------------

    /**
     * Zapamiętuje adresy treści i jej potomków (strony hierarchiczne) przed zapisem.
     *
     * @return array<int, array{path: string, live: bool}>
     */
    public function snapshot(Content $content): array
    {
        if (! $content->exists) {
            return [];
        }

        $snapshot = [];
        $queue = [$content->id];

        for ($i = 0; $queue !== [] && count($snapshot) < 500 && $i < 50; $i++) {
            $items = Content::with('type', 'parent')->whereIn('id', $queue)->get();
            $queue = Content::whereIn('parent_id', $queue)->pluck('id')->all();

            foreach ($items as $item) {
                $snapshot[$item->id] = ['path' => '/'.$this->paths->pathFor($item), 'live' => $this->isLive($item)];
            }
        }

        return $snapshot;
    }

    /** Po zapisie: dla zmienionych adresów opublikowanych treści tworzy 301, a zajęte adresy zwalnia. */
    public function recordMoves(array $before): void
    {
        foreach ($before as $id => $old) {
            $item = Content::with('type', 'parent')->find($id);

            if (! $item) {
                continue;
            }

            $new = '/'.$this->paths->pathFor($item);

            // Adres, na którym treść jest teraz, nie może być przesłonięty automatycznym przekierowaniem.
            Redirect::where('auto', true)->where('source', mb_strtolower($new))->delete();

            if ($old['live'] && mb_strtolower($old['path']) !== mb_strtolower($new) && $this->isLive($item)) {
                try {
                    $this->create($old['path'], $new, 301, ['auto' => true, 'note' => 'Zmiana adresu: '.$item->title]);
                } catch (InvalidArgumentException) {
                    // Adres zarezerwowany albo już przekierowany ręcznie: nic nie robimy.
                }
            }
        }
    }

    private function isLive(Content $content): bool
    {
        return Content::published()->whereKey($content->id)->exists();
    }

    // --- Monitor 404 -------------------------------------------------------------------------

    public function logNotFound(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->is('cms', 'cms/*')) {
            return;
        }

        $path = mb_strtolower('/'.trim($request->path(), '/'));

        if (mb_strlen($path) > 255) {
            return;
        }

        $referrer = $request->headers->get('referer');
        $referrer = $referrer ? mb_substr($referrer, 0, 255) : null;

        $log = NotFoundLog::firstOrNew(['path' => $path]);
        $log->hits = $log->exists ? $log->hits + 1 : 1;
        $log->forceFill(['last_seen_at' => now(), 'last_referrer' => $referrer ?? $log->last_referrer])->save();

        if (NotFoundLog::count() > self::LOG_LIMIT + 100) {
            NotFoundLog::orderBy('last_seen_at')->limit(100)->get()->each->delete();
        }
    }
}
