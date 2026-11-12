<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Support\HtmlString;

class TextareaField extends AbstractFieldType
{
    public function name(): string
    {
        return 'textarea';
    }

    public function label(): string
    {
        return 'Text Area';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return [
            'default_value' => '', 'placeholder' => '', 'maxlength' => '', 'rows' => '',
            // wpautop: akapity i <br> | br: tylko <br> | '' : surowy tekst
            'new_lines' => 'wpautop',
        ];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'string'];

        if (($max = $this->number($field, 'maxlength')) && $max > 0) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }

    /** Tekst jest zawsze escapowany, a formatowanie dodaje tylko znaczniki akapitów i <br>. */
    public function format(mixed $value, array $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;

        return match ($field['new_lines']) {
            'wpautop' => new HtmlString(
                collect(preg_split('/\R{2,}/', trim($text)))
                    ->map(fn (string $p) => '<p>'.nl2br(e(trim($p))).'</p>')
                    ->implode("\n")
            ),
            'br' => new HtmlString(nl2br(e($text))),
            default => $text,
        };
    }
}
