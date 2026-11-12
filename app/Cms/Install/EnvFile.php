<?php

namespace App\Cms\Install;

use RuntimeException;

/** Bezpieczny zapis pliku .env: podmienia istniejące klucze (także zakomentowane), reszty nie rusza. */
class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    public function get(string $key): ?string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $this->read(), $m)) {
            return null;
        }

        return trim($m[1], " \t\"'");
    }

    /** @param  array<string, string|int|bool|null>  $values  null komentuje klucz */
    public function set(array $values): void
    {
        $text = $this->read();

        foreach ($values as $key => $value) {
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

            if ($value === null) {
                $text = preg_replace($pattern, '# '.$key.'=', $text, 1) ?? $text;

                continue;
            }

            $line = $key.'='.$this->format($value);

            if (preg_match($pattern, $text)) {
                $text = preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $text, 1) ?? $text;
            } else {
                $text = rtrim($text, "\n")."\n".$line."\n";
            }
        }

        if (@file_put_contents($this->path, $text, LOCK_EX) === false) {
            throw new RuntimeException('Cannot write '.basename($this->path));
        }
    }

    private function read(): string
    {
        return is_file($this->path) ? (string) file_get_contents($this->path) : '';
    }

    private function format(string|int|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=\-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$', "\n", "\r"], ['\\\\', '\\"', '\\$', '', ''], $value).'"';
    }
}
