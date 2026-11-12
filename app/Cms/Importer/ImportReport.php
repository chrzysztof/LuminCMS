<?php

namespace App\Cms\Importer;

/** Wynik importu: liczniki per sekcja, błędy (z miejscem w pliku) i uwagi. */
class ImportReport
{
    public const SECTIONS = ['content_types', 'taxonomies', 'terms', 'field_groups', 'contents'];

    /** @var array<string, array{created: int, updated: int, skipped: int}> */
    public array $sections = [];

    /** @var list<array{where: string, message: string}> */
    public array $errors = [];

    /** @var list<string> */
    public array $notes = [];

    public function __construct(public readonly bool $dryRun)
    {
        foreach (self::SECTIONS as $section) {
            $this->sections[$section] = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }
    }

    public function count(string $section, string $what): void
    {
        $this->sections[$section][$what]++;
    }

    public function error(string $where, string $message): void
    {
        $this->errors[] = ['where' => $where, 'message' => $message];
    }

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /** Czy zmiany zostały zapisane (brak błędów i nie jest to próba). */
    public function applied(): bool
    {
        return ! $this->dryRun && ! $this->failed();
    }

    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'applied' => $this->applied(),
            'sections' => $this->sections,
            'errors' => $this->errors,
            'notes' => $this->notes,
        ];
    }
}
