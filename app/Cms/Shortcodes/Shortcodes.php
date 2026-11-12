<?php

namespace App\Cms\Shortcodes;

use App\Cms\Content\Content;
use Closure;

/**
 * Shortcody w treści: [nazwa atrybut="wartość"], [nazwa]treść[/nazwa], [nazwa /].
 * Zapis [[nazwa]] wypisuje dosłownie [nazwa]. Nieznane kody zostają bez zmian,
 * a zawartość <pre>, <code>, <script> i <style> nie jest przetwarzana.
 *
 * Własny shortcode: app(Shortcodes::class)->add('nazwa', fn (array $attrs, string $content, ?Content $current) => '<b>…</b>');
 * Handler zwraca zaufany HTML, więc wartości z atrybutów musi sam escapować.
 */
class Shortcodes
{
    private const MAX_DEPTH = 8;

    /** @var array<string, Closure> */
    private array $handlers = [];

    /** @var array<string, array{usage: string, description: string}> dokumentacja do panelu */
    private array $docs = [];

    public function add(string $name, Closure $handler, string $usage = '', string $description = ''): void
    {
        $name = strtolower($name);

        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException("Niepoprawna nazwa shortcode: {$name}");
        }

        $this->handlers[$name] = $handler;
        $this->docs[$name] = ['usage' => $usage ?: '['.$name.']', 'description' => $description];
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[strtolower($name)]);
    }

    /** @return array<string, array{usage: string, description: string}> nazwa => przykład użycia i opis (klucz tłumaczenia) */
    public function all(): array
    {
        ksort($this->docs);

        return $this->docs;
    }

    public function render(?string $text, ?Content $current = null): string
    {
        $text = (string) $text;

        if ($this->handlers === [] || ! str_contains($text, '[')) {
            return $text;
        }

        // Fragmenty kodu zastępujemy znacznikami, żeby przykłady w <code> i <pre> zostały nietknięte.
        $protected = [];
        $text = preg_replace_callback('~<(pre|code|script|style)\b[^>]*>.*?</\1>~is', function (array $m) use (&$protected) {
            $protected[] = $m[0];

            return "\u{E000}LM".(count($protected) - 1)."\u{E001}";
        }, $text) ?? $text;

        $text = $this->process($text, $current, 0);

        return preg_replace_callback('~\x{E000}LM(\d+)\x{E001}~u', fn (array $m) => $protected[(int) $m[1]] ?? '', $text) ?? $text;
    }

    private function process(string $text, ?Content $current, int $depth): string
    {
        if ($depth > self::MAX_DEPTH || ! str_contains($text, '[')) {
            return $text;
        }

        $names = implode('|', array_map(fn ($n) => preg_quote($n, '~'), array_keys($this->handlers)));
        $pattern = '~\[(\[?)('.$names.')(?![\w-])([^\]]*?)(\/)?\](?:(.*?)\[\/\2\])?(\]?)~is';

        return preg_replace_callback($pattern, function (array $m) use ($current, $depth) {
            // [[nazwa]] => dosłowne [nazwa]
            if ($m[1] === '[' && $m[6] === ']') {
                return substr($m[0], 1, -1);
            }

            $name = strtolower($m[2]);
            $inner = ($m[4] ?? '') === '/' ? '' : $this->process($m[5] ?? '', $current, $depth + 1);
            $output = ($this->handlers[$name])($this->attributes($m[3]), $inner, $current);

            return $m[1].$output.$m[6];
        }, $text) ?? $text;
    }

    /** @return array<int|string, string> atrybuty nazwane oraz pozycyjne (klucze liczbowe) */
    private function attributes(string $raw): array
    {
        // Edytory zamieniają cudzysłowy na typograficzne; traktujemy je jak zwykłe.
        $raw = str_replace(['“', '”', '„', '‟', '″', '&quot;', '&#8221;', '&#8220;', '&#8222;'], '"', $raw);
        $raw = str_replace(['‘', '’', '&#8217;', '&#8216;'], "'", $raw);
        $attrs = [];

        preg_match_all('~([\w-]+)\s*=\s*"([^"]*)"|([\w-]+)\s*=\s*\'([^\']*)\'|([\w-]+)\s*=\s*([^\s"\']+)|"([^"]*)"|(\S+)~', $raw, $all, PREG_SET_ORDER);

        foreach ($all as $m) {
            if (($m[1] ?? '') !== '') {
                $attrs[strtolower($m[1])] = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
            } elseif (($m[3] ?? '') !== '') {
                $attrs[strtolower($m[3])] = html_entity_decode($m[4], ENT_QUOTES | ENT_HTML5);
            } elseif (($m[5] ?? '') !== '') {
                $attrs[strtolower($m[5])] = html_entity_decode($m[6], ENT_QUOTES | ENT_HTML5);
            } elseif (($m[7] ?? '') !== '') {
                $attrs[] = html_entity_decode($m[7], ENT_QUOTES | ENT_HTML5);
            } elseif (($m[8] ?? '') !== '') {
                $attrs[] = $m[8];
            }
        }

        return $attrs;
    }
}
