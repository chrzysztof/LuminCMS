<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Support\HtmlSanitizer;
use Illuminate\Support\HtmlString;

class WysiwygField extends AbstractFieldType
{
    public function name(): string
    {
        return 'wysiwyg';
    }

    public function label(): string
    {
        return __('Edytor tekstu');
    }

    public function category(): string
    {
        return 'content';
    }

    protected function defaults(): array
    {
        return ['default_value' => '', 'toolbar' => 'full', 'min_height' => 200];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'max:500000'];
    }

    /** HTML jest czyszczony przy zapisie; pusty akapit z edytora traktujemy jak brak wartości. */
    public function normalize(mixed $value, array $field): mixed
    {
        $clean = app(HtmlSanitizer::class)->clean(is_string($value) ? $value : '');

        return $this->isEmpty($clean) ? null : $clean;
    }

    public function isEmpty(mixed $value): bool
    {
        if (! is_string($value)) {
            return $value === null || $value === [];
        }

        return trim(strip_tags($value, '<img><hr><table>')) === '';
    }

    public function format(mixed $value, array $field): mixed
    {
        return $value === null ? null : new HtmlString(app(Shortcodes::class)->render((string) $value));
    }
}
