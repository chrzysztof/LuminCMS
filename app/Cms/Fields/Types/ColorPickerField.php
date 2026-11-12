<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class ColorPickerField extends AbstractFieldType
{
    private const HEX = '#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})';

    private const RGBA = 'rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)';

    public function name(): string
    {
        return 'color_picker';
    }

    public function label(): string
    {
        return 'Color Picker';
    }

    public function category(): string
    {
        return 'advanced';
    }

    protected function defaults(): array
    {
        // return_format: string (np. #ff0000) | array (red, green, blue, alpha)
        return ['default_value' => '', 'enable_opacity' => false, 'return_format' => 'string'];
    }

    /** Bez przezroczystości tylko hex, z przezroczystością także rgb() i rgba(). */
    public function rules(array $field): array
    {
        $pattern = $field['enable_opacity']
            ? '/^('.self::HEX.'|'.self::RGBA.')$/'
            : '/^'.self::HEX.'$/';

        return [...$this->baseRules($field), 'string', "regex:{$pattern}"];
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($value === null || $field['return_format'] !== 'array') {
            return $value;
        }

        return $this->toArray((string) $value);
    }

    private function toArray(string $color): ?array
    {
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $m)) {
            $color = '#'.preg_replace('/(.)/', '$1$1', $m[1]);
        }

        if (preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', $color, $m)) {
            return ['red' => hexdec($m[1]), 'green' => hexdec($m[2]), 'blue' => hexdec($m[3]), 'alpha' => 1];
        }

        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $color, $m)) {
            return ['red' => (int) $m[1], 'green' => (int) $m[2], 'blue' => (int) $m[3], 'alpha' => (float) ($m[4] ?? 1)];
        }

        return null;
    }
}
