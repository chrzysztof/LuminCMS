<?php

namespace App\Cms\Fields\Types;

use App\Cms\Media\Media;

class GalleryField extends MediaFieldBase
{
    public function name(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Galeria';
    }

    protected function imageOnly(): bool
    {
        return true;
    }

    protected function defaults(): array
    {
        return [...parent::defaults(), 'min' => '', 'max' => '', 'min_width' => '', 'min_height' => '', 'max_width' => '', 'max_height' => '', 'insert' => 'append'];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'array'];

        if (($min = $this->number($field, 'min')) !== null) {
            $rules[] = "min:{$min}";
        }
        if (($max = $this->number($field, 'max')) !== null) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }

    public function itemRules(array $field): array
    {
        return $this->idRules($field);
    }

    /** Lista identyfikatorów w kolejności ustawionej przez użytkownika, bez duplikatów. */
    public function normalize(mixed $value, array $field): mixed
    {
        $ids = collect(is_array($value) ? $value : [])->map(fn ($v) => $this->toId($v))->filter()->unique()->values()->all();

        return $ids ?: null;
    }

    public function format(mixed $value, array $field): mixed
    {
        $ids = is_array($value) ? array_values(array_filter(array_map(fn ($v) => $this->toId($v), $value))) : [];

        if (! $ids) {
            return [];
        }

        $items = Media::whereIn('id', $ids)->get()->keyBy('id');

        // Zachowaj kolejność galerii; pomiń pliki usunięte z biblioteki.
        return collect($ids)->map(fn ($id) => $this->present($items->get($id), $field))->filter(fn ($v) => $v !== null)->values()->all();
    }
}
