<?php

namespace App\Cms\Fields\Types;

use App\Cms\Media\Media;

class ImageField extends MediaFieldBase
{
    public function name(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Obraz';
    }

    protected function imageOnly(): bool
    {
        return true;
    }

    protected function defaults(): array
    {
        return [...parent::defaults(), 'min_width' => '', 'min_height' => '', 'max_width' => '', 'max_height' => '', 'preview_size' => 'medium'];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), ...$this->idRules($field)];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->toId($value);
    }

    public function format(mixed $value, array $field): mixed
    {
        $id = $this->toId($value);

        return $id ? $this->present(Media::find($id), $field) : null;
    }
}
