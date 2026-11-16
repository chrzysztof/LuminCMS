<?php

namespace App\Cms\Updates;

/** Wydanie systemu opublikowane w GitHub Releases. */
final readonly class Release
{
    public function __construct(
        public string $version,
        public string $name,
        public string $notes,
        public string $url,
        public ?string $publishedAt,
        public ?string $assetName,
        public ?string $assetUrl,
        public int $assetSize,
        /** Suma SHA-256 paczki z API GitHuba (pole "digest"), jeśli jest dostępna. */
        public ?string $sha256,
        /** Adres pliku .sha256 dołączonego do wydania - alternatywa dla sumy z API. */
        public ?string $checksumUrl,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['version'], (string) $data['name'], (string) $data['notes'], (string) $data['url'],
            $data['publishedAt'] ?? null, $data['assetName'] ?? null, $data['assetUrl'] ?? null,
            (int) ($data['assetSize'] ?? 0), $data['sha256'] ?? null, $data['checksumUrl'] ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** Czy wydanie ma paczkę i sumę kontrolną, więc da się je zainstalować z panelu. */
    public function installable(): bool
    {
        return $this->assetUrl !== null && ($this->sha256 !== null || $this->checksumUrl !== null);
    }
}
