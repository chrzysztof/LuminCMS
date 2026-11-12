<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

/**
 * Odnośnik: adres, tekst i sposób otwarcia (ta sama karta albo nowa).
 * Dozwolone adresy: http(s), względne (/ścieżka, #kotwica), mailto: i tel:. Schematy typu javascript: są odrzucane.
 */
class LinkField extends AbstractFieldType
{
    private const URL = '~^(https?://\S+|//[^\s/]\S*|/(?!/)\S*|#\S*|mailto:\S+|tel:\+?[\d\s().\-]+)$~i';

    public function name(): string
    {
        return 'link';
    }

    public function label(): string
    {
        return __('Odnośnik');
    }

    public function category(): string
    {
        return 'relational';
    }

    protected function defaults(): array
    {
        return ['return_format' => 'array']; // array | url
    }

    public function rules(array $field): array
    {
        return [
            ...$this->baseRules($field), 'array',
            fn (string $a, mixed $v, \Closure $fail) => ! ($field['required'] && $this->isEmpty($v)) ?: $fail(__('validation.required', ['attribute' => $a])),
            fn (string $a, mixed $v, \Closure $fail) => $this->valid($v) ?: $fail(__('Podaj poprawny adres odnośnika (http://, https://, /ścieżka, #kotwica, mailto: lub tel:).')),
        ];
    }

    private function valid(mixed $v): bool
    {
        if ($v === null || $v === []) {
            return true;
        }

        if (! is_array($v)) {
            return false;
        }

        $url = $v['url'] ?? '';
        $title = $v['title'] ?? '';

        return is_string($url) && ($url === '' || (mb_strlen($url) <= 2000 && preg_match(self::URL, trim($url)) === 1))
            && (is_string($title) || $title === null) && mb_strlen((string) $title) <= 255
            && in_array($v['target'] ?? '', ['', '_blank', null], true);
    }

    public function normalize(mixed $value, array $field): mixed
    {
        if (! is_array($value) || trim((string) ($value['url'] ?? '')) === '') {
            return null;
        }

        return [
            'url' => trim((string) $value['url']),
            'title' => trim((string) ($value['title'] ?? '')),
            'target' => ($value['target'] ?? '') === '_blank' ? '_blank' : '',
        ];
    }

    public function isEmpty(mixed $value): bool
    {
        return ! is_array($value) || trim((string) ($value['url'] ?? '')) === '';
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        return ($field['return_format'] ?? 'array') === 'url'
            ? $value['url']
            : ['url' => $value['url'], 'title' => $value['title'] ?? '', 'target' => $value['target'] ?? ''];
    }
}
