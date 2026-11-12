<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Media\Media;
use Closure;
use Illuminate\Validation\Rule;

/** Wspólna logika pól wskazujących pliki z biblioteki mediów (Image, File, Gallery). */
abstract class MediaFieldBase extends AbstractFieldType
{
    public function category(): string
    {
        return 'content';
    }

    protected function defaults(): array
    {
        return [
            'return_format' => 'array',   // array | url | id
            'size' => 'full',             // rozmiar używany dla url/array
            'mime_types' => '',           // np. "pdf, jpg"; puste = bez ograniczeń
            'max_size' => '',             // MB; puste = limit biblioteki
        ];
    }

    /** Reguły pojedynczego identyfikatora pliku z biblioteki. */
    protected function idRules(array $field): array
    {
        return [
            'integer',
            Rule::exists('media', 'id'),
            $this->constraints($field),
        ];
    }

    /** Sprawdza typ, rozmiar i wymiary pliku względem ustawień pola. */
    protected function constraints(array $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field) {
            $media = Media::find($value);

            if (! $media) {
                return;
            }

            if ($this->imageOnly() && ! $media->isImage()) {
                $fail(__('Wybrany plik nie jest obrazem.'));

                return;
            }

            $allowed = array_filter(array_map(fn ($e) => strtolower(trim($e, ". \t")), explode(',', (string) $field['mime_types'])));

            if ($allowed && ! in_array(strtolower(pathinfo($media->path, PATHINFO_EXTENSION)), $allowed, true)) {
                $fail(__('Niedozwolony typ pliku. Dozwolone: :types.', ['types' => implode(', ', $allowed)]));
            }

            $maxMb = $this->number($field, 'max_size');
            if ($maxMb !== null && $media->size > $maxMb * 1024 * 1024) {
                $fail("Plik jest większy niż {$maxMb} MB.");
            }

            foreach (['min_width' => ['width', '<'], 'min_height' => ['height', '<'], 'max_width' => ['width', '>'], 'max_height' => ['height', '>']] as $key => [$prop, $op]) {
                $limit = $this->number($field, $key);
                if ($limit === null || ! $media->isImage()) {
                    continue;
                }
                if (($op === '<' && $media->{$prop} < $limit) || ($op === '>' && $media->{$prop} > $limit)) {
                    $fail($op === '<'
                        ? __($prop === 'width' ? 'Minimalna szerokość obrazu to :limit px.' : 'Minimalna wysokość obrazu to :limit px.', ['limit' => $limit])
                        : __($prop === 'width' ? 'Maksymalna szerokość obrazu to :limit px.' : 'Maksymalna wysokość obrazu to :limit px.', ['limit' => $limit]));
                }
            }
        };
    }

    protected function imageOnly(): bool
    {
        return false;
    }

    protected function toId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** Pojedynczy plik w formacie zwrotnym pola. */
    protected function present(?Media $media, array $field): mixed
    {
        if (! $media) {
            return null;
        }

        return match ($field['return_format']) {
            'id' => $media->id,
            'url' => $media->url($field['size']),
            default => $media->asArray($field['size']),
        };
    }
}
