<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FormatsSubFields;

/**
 * Lista wierszy, z których każdy ma jeden z zadeklarowanych układów (layouts) z własnym zestawem pól.
 * Wiersz zapisujemy jako `['_layout' => 'nazwa', ...wartości pól układu]`.
 */
class FlexibleContentField extends AbstractFieldType implements ContainerFieldType
{
    use FormatsSubFields;

    public const MAX_ROWS = 200;

    public const LAYOUT_KEY = '_layout';

    public function name(): string
    {
        return 'flexible_content';
    }

    public function label(): string
    {
        return 'Flexible Content';
    }

    public function category(): string
    {
        return 'layout';
    }

    protected function defaults(): array
    {
        return ['layouts' => [], 'min' => '', 'max' => '', 'button_label' => __('Dodaj układ')];
    }

    /** Układy po nazwie: ['name' => ['name', 'label', 'min', 'max', 'sub_fields']]. */
    public function layouts(array $field): array
    {
        $out = [];

        foreach ($this->listOfArrays($field['layouts'] ?? []) as $layout) {
            $name = (string) ($layout['name'] ?? '');

            if ($name !== '' && ! isset($out[$name])) {
                $out[$name] = [
                    'name' => $name,
                    'label' => (string) ($layout['label'] ?? '') ?: $name,
                    'min' => is_numeric($layout['min'] ?? null) ? (int) $layout['min'] : null,
                    'max' => is_numeric($layout['max'] ?? null) ? (int) $layout['max'] : null,
                    'sub_fields' => $this->listOfArrays($layout['sub_fields'] ?? []),
                ];
            }
        }

        return $out;
    }

    public function subFields(array $field): array
    {
        return array_merge([], ...array_column($this->layouts($field), 'sub_fields'));
    }

    public function entries(array $field, mixed $value): array
    {
        $layouts = $this->layouts($field);
        $entries = [];

        foreach (is_array($value) ? $value : [] as $index => $row) {
            $name = is_array($row) ? ($row[self::LAYOUT_KEY] ?? null) : null;

            if (is_string($name) && isset($layouts[$name])) {
                $entries[] = ['path' => (string) $index, 'row' => $row, 'fields' => $layouts[$name]['sub_fields'], 'extra' => [self::LAYOUT_KEY => $name]];
            }
        }

        return $entries;
    }

    public function assemble(array $field, array $entries): mixed
    {
        return array_map(fn (array $e) => $e['extra'] + $e['values'], $entries) ?: null;
    }

    /** Pierwszy segment ścieżki to nazwa układu. */
    public function descend(array $field, array $path): array
    {
        $layout = array_shift($path);

        return [$this->layouts($field)[$layout]['sub_fields'] ?? [], $path];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'array'];
        $max = $this->number($field, 'max');
        $rules[] = 'max:'.($max !== null ? min($max, self::MAX_ROWS) : self::MAX_ROWS);

        if (($min = $this->number($field, 'min')) !== null) {
            $rules[] = "min:{$min}";
        }

        $layouts = $this->layouts($field);

        // Znane układy i limity liczby wierszy każdego układu.
        $rules[] = function (string $attribute, mixed $value, \Closure $fail) use ($layouts): void {
            $counts = [];

            foreach ((array) $value as $row) {
                $name = is_array($row) ? ($row[self::LAYOUT_KEY] ?? null) : null;

                if (! is_string($name) || ! isset($layouts[$name])) {
                    $fail(__('Nieznany układ wiersza.'));

                    return;
                }

                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }

            foreach ($layouts as $name => $layout) {
                $count = $counts[$name] ?? 0;

                if ($layout['max'] !== null && $count > $layout['max']) {
                    $fail(__('Układ „:label” może wystąpić najwyżej :n razy.', ['label' => $layout['label'], 'n' => $layout['max']]));
                } elseif ($layout['min'] !== null && $count < $layout['min']) {
                    $fail(__('Układ „:label” musi wystąpić co najmniej :n razy.', ['label' => $layout['label'], 'n' => $layout['min']]));
                }
            }
        };

        return $rules;
    }

    public function itemRules(array $field): array
    {
        return ['array'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->listOfArrays($value) ?: null;
    }

    /** Lista wierszy; każdy z kluczem `_layout` i sformatowanymi wartościami pól swojego układu. */
    public function format(mixed $value, array $field): mixed
    {
        $layouts = $this->layouts($field);
        $out = [];

        foreach (is_array($value) ? $value : [] as $row) {
            $name = is_array($row) ? ($row[self::LAYOUT_KEY] ?? null) : null;

            if (is_string($name) && isset($layouts[$name])) {
                $out[] = [self::LAYOUT_KEY => $name] + $this->formatRow($row, $layouts[$name]['sub_fields']);
            }
        }

        return $out;
    }
}
