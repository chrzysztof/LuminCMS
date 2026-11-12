<?php

namespace App\Cms\Fields\Types;

use App\Cms\Media\Media;

class FileField extends MediaFieldBase
{
    public function name(): string
    {
        return 'file';
    }

    public function label(): string
    {
        return 'Plik';
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), ...$this->idRules($field)];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->toId($value);
    }

    /** Dla plików "url" to adres oryginału (warianty dotyczą tylko obrazów). */
    public function format(mixed $value, array $field): mixed
    {
        $id = $this->toId($value);

        return $id ? $this->present(Media::find($id), $field) : null;
    }
}
