<?php

namespace App\Cms\Builder;

/** Zbiera własne style elementów (css_cache) treści i części użytych na bieżącej stronie; drukowane raz w <head>. */
class LocalCss
{
    /** @var array<string, string> */
    private array $chunks = [];

    public function add(?string $css): void
    {
        $css = trim((string) $css);

        if ($css !== '') {
            $this->chunks[md5($css)] = $css;
        }
    }

    public function reset(): void
    {
        $this->chunks = [];
    }

    public function css(): string
    {
        return implode("\n", $this->chunks);
    }
}
