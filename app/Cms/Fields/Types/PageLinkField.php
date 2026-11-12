<?php

namespace App\Cms\Fields\Types;

use App\Cms\Content\ContentResolver;

/**
 * Odnośnik do treści serwisu (strony, wpisu): zapisujemy identyfikator, więc adres
 * zmienia się razem ze slugiem. Zwraca adres URL albo identyfikator.
 */
class PageLinkField extends PostObjectField
{
    public function name(): string
    {
        return 'page_link';
    }

    public function label(): string
    {
        return __('Odnośnik do treści');
    }

    protected function defaults(): array
    {
        return [...parent::defaults(), 'return_format' => 'url']; // url | id
    }

    public function format(mixed $value, array $field): mixed
    {
        $ids = $this->toIds($value);

        if (($field['return_format'] ?? 'url') === 'id') {
            return $this->multiple($field) ? $ids : ($ids[0] ?? null);
        }

        $resolver = app(ContentResolver::class);
        $urls = $this->load($field, $ids)->map(fn ($c) => $resolver->urlFor($c))->values();

        return $this->multiple($field) ? $urls->all() : $urls->first();
    }
}
