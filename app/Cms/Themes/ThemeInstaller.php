<?php

namespace App\Cms\Themes;

use DomainException;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Instalacja motywu z ZIP-a, eksport do ZIP-a i usuwanie. Archiwum jest sprawdzane przed rozpakowaniem:
 * bezpieczne ścieżki (bez "..", bez dowiązań), biała lista rozszerzeń (PHP tylko jako *.blade.php),
 * limity liczby plików i rozmiaru po rozpakowaniu oraz poprawny theme.json.
 */
class ThemeInstaller
{
    public const MAX_FILES = 1500;

    public const MAX_UNPACKED = 40 * 1024 * 1024;

    private const EXTENSIONS = ['json', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'md', 'txt'];

    public function __construct(private readonly ThemeManager $themes, private readonly string $directory) {}

    /**
     * @param  string  $fallbackSlug  identyfikator, gdy archiwum nie ma folderu głównego (np. z nazwy pliku)
     *
     * @throws DomainException z komunikatem dla użytkownika
     */
    public function install(string $zipPath, string $fallbackSlug): Theme
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new DomainException(__('To nie jest poprawne archiwum ZIP.'));
        }

        try {
            [$slug, $prefix, $entries] = $this->inspect($zip, $fallbackSlug);
            $manifest = json_decode((string) $zip->getFromName($prefix.'theme.json'), true);

            if (! is_array($manifest) || trim((string) ($manifest['name'] ?? '')) === '') {
                throw new DomainException(__('Plik theme.json jest niepoprawny albo nie ma nazwy motywu.'));
            }

            $this->checkStructure($manifest, $entries, $prefix);

            $target = $this->directory.'/'.$slug;

            if (file_exists($target) || $this->themes->has($slug)) {
                throw new DomainException(__('Motyw o identyfikatorze „:slug” jest już zainstalowany. Usuń go najpierw, jeśli chcesz wgrać nową wersję.', ['slug' => $slug]));
            }

            $this->extract($zip, $entries, $prefix, $target);
        } finally {
            $zip->close();
        }

        $this->themes->refresh();

        return $this->themes->all()[$slug];
    }

    /** Ścieżka do tymczasowego ZIP-a motywu (z folderem głównym o nazwie motywu); plik usuwa kontroler po wysłaniu. */
    public function export(Theme $theme): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lumin-theme-');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $root = realpath($theme->path);

        foreach (File::allFiles($theme->path) as $file) {
            $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen($root) + 1));

            if (! str_starts_with(basename($relative), '.')) {
                $zip->addFile($file->getRealPath(), $theme->slug.'/'.$relative);
            }
        }

        $zip->close();

        return $tmp;
    }

    /** @throws DomainException */
    public function delete(string $slug): void
    {
        $theme = $this->themes->all()[$slug] ?? null;

        if (! $theme) {
            throw new DomainException(__('Nie ma takiego motywu.'));
        }

        if ($slug === ThemeManager::DEFAULT) {
            throw new DomainException(__('Motywu domyślnego nie można usunąć.'));
        }

        if ($this->themes->active()->slug === $slug) {
            throw new DomainException(__('Nie można usunąć aktywnego motywu.'));
        }

        foreach ($this->themes->all() as $other) {
            if ($other->parentSlug() === $slug) {
                throw new DomainException(__('Ten motyw jest motywem nadrzędnym motywu „:name”.', ['name' => $other->name()]));
            }
        }

        $real = realpath($theme->path);
        $base = realpath($this->directory);

        if ($real === false || $base === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            throw new DomainException(__('Nie ma takiego motywu.'));
        }

        File::deleteDirectory($real);
        $this->themes->refresh();
    }

    /**
     * Waliduje wpisy archiwum i ustala identyfikator motywu oraz prefiks folderu głównego.
     *
     * @return array{0: string, 1: string, 2: list<array{index: int, name: string}>}
     */
    private function inspect(ZipArchive $zip, string $fallbackSlug): array
    {
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_FILES) {
            throw new DomainException(__('Archiwum jest puste albo ma zbyt wiele plików.'));
        }

        $entries = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store') {
                continue;
            }

            if (! $this->safePath($name) || $this->isSymlink($zip, $i) || ! $this->allowedFile($name)) {
                throw new DomainException(__('Archiwum zawiera niedozwolony plik: :name', ['name' => mb_substr($name, 0, 80)]));
            }

            $total += (int) ($zip->statIndex($i)['size'] ?? 0);

            if ($total > self::MAX_UNPACKED) {
                throw new DomainException(__('Rozpakowany motyw jest zbyt duży.'));
            }

            $entries[] = ['index' => $i, 'name' => $name];
        }

        $names = array_column($entries, 'name');
        $roots = array_unique(array_map(fn ($n) => explode('/', $n)[0], $names));

        if (in_array('theme.json', $names, true)) {
            $slug = $fallbackSlug;
            $prefix = '';
        } elseif (count($roots) === 1 && in_array($roots[0].'/theme.json', $names, true)) {
            $slug = $roots[0];
            $prefix = $roots[0].'/';
        } else {
            throw new DomainException(__('W archiwum nie znaleziono pliku theme.json.'));
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $slug)) {
            throw new DomainException(__('Identyfikator motywu może zawierać tylko małe litery, cyfry, myślnik i podkreślenie.'));
        }

        return [$slug, $prefix, $entries];
    }

    /** @param  list<array{index: int, name: string}>  $entries */
    private function checkStructure(array $manifest, array $entries, string $prefix): void
    {
        $parent = $manifest['parent'] ?? null;

        if ($parent !== null && (! is_string($parent) || ! $this->themes->has($parent))) {
            throw new DomainException(__('Motyw nadrzędny „:parent” nie jest zainstalowany.', ['parent' => is_string($parent) ? $parent : '']));
        }

        if ($parent === null && ! in_array($prefix.'views/layout.blade.php', array_column($entries, 'name'), true)) {
            throw new DomainException(__('Motyw musi mieć plik views/layout.blade.php albo wskazywać motyw nadrzędny.'));
        }
    }

    /** @param  list<array{index: int, name: string}>  $entries */
    private function extract(ZipArchive $zip, array $entries, string $prefix, string $target): void
    {
        $tmp = $this->directory.'/.install-'.bin2hex(random_bytes(6));
        $written = 0;

        try {
            foreach ($entries as $entry) {
                $relative = substr($entry['name'], strlen($prefix));

                if ($relative === '' || ! str_starts_with($entry['name'], $prefix)) {
                    continue;
                }

                $dest = $tmp.'/'.$relative;
                File::ensureDirectoryExists(dirname($dest));

                $in = $zip->getStream($entry['name']);
                $out = fopen($dest, 'wb');

                if ($in === false || $out === false) {
                    throw new DomainException(__('Nie udało się rozpakować archiwum.'));
                }

                // Rzeczywisty rozmiar liczymy przy kopiowaniu (nagłówek ZIP-a może kłamać).
                while (! feof($in)) {
                    $chunk = (string) fread($in, 65536);
                    $written += strlen($chunk);

                    if ($written > self::MAX_UNPACKED) {
                        throw new DomainException(__('Rozpakowany motyw jest zbyt duży.'));
                    }

                    fwrite($out, $chunk);
                }

                fclose($in);
                fclose($out);
            }

            File::moveDirectory($tmp, $target);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    private function safePath(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, "\0")
            && ! str_contains($name, '\\')
            && ! str_contains($name, ':')
            && ! str_starts_with($name, '/')
            && ! in_array('..', explode('/', $name), true)
            && ! in_array('.', explode('/', $name), true);
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;
        $zip->getExternalAttributesIndex($index, $opsys, $attr);

        return $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000;
    }

    private function allowedFile(string $name): bool
    {
        $base = basename($name);

        if (str_starts_with($base, '.')) {
            return false;
        }

        if (str_ends_with($base, '.blade.php')) {
            return true;
        }

        return in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }
}
