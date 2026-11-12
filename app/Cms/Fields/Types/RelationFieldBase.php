<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Wspólna logika pól wskazujących rekordy (treści, terminy, użytkownicy).
 * Zapisujemy listę identyfikatorów; return_format wybiera obiekty albo same identyfikatory.
 */
abstract class RelationFieldBase extends AbstractFieldType
{
    public function category(): string
    {
        return 'relational';
    }

    abstract protected function query(array $field): Builder;

    protected function multiple(array $field): bool
    {
        return true;
    }

    protected function defaults(): array
    {
        return ['return_format' => 'object', 'min' => '', 'max' => '']; // object | id
    }

    protected function toIds(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), $this->multiple($field) ? 'array' : 'integer'];

        if ($this->multiple($field)) {
            if (($min = $this->number($field, 'min')) !== null) {
                $rules[] = "min:{$min}";
            }
            if (($max = $this->number($field, 'max')) !== null) {
                $rules[] = "max:{$max}";
            }
        }

        if (! $this->multiple($field)) {
            $rules[] = fn (string $a, mixed $v, \Closure $fail) => $this->allowed($field, [(int) $v]) ?: $fail(__('Wybrana pozycja jest niedozwolona.'));
        }

        return $rules;
    }

    public function itemRules(array $field): array
    {
        if (! $this->multiple($field)) {
            return [];
        }

        return ['integer', fn (string $a, mixed $v, \Closure $fail) => $this->allowed($field, [(int) $v]) ?: $fail(__('Wybrana pozycja jest niedozwolona.'))];
    }

    /** Czy wszystkie identyfikatory istnieją i spełniają ograniczenia pola. */
    protected function allowed(array $field, array $ids): bool
    {
        return $this->query($field)->whereKey($ids)->count() === count(array_unique($ids));
    }

    public function normalize(mixed $value, array $field): mixed
    {
        $ids = $this->toIds($value);

        if ($ids === []) {
            return null;
        }

        return $this->multiple($field) ? $ids : $ids[0];
    }

    /** Rekordy w kolejności zapisanych identyfikatorów; usunięte pomijamy. */
    protected function load(array $field, array $ids): Collection
    {
        $items = $this->query($field)->whereKey($ids)->get()->keyBy('id');

        return $items->only($ids)->sortBy(fn ($i) => array_search($i->getKey(), $ids, true))->values();
    }

    public function format(mixed $value, array $field): mixed
    {
        $ids = $this->toIds($value);
        $asId = $field['return_format'] === 'id';
        $items = $asId ? collect($ids) : $this->load($field, $ids);

        if (! $this->multiple($field)) {
            return $items->first();
        }

        return $items->all();
    }

    /** Etykiety wybranych rekordów dla panelu: id => {id, label, meta}. */
    abstract public function labels(array $field, array $ids): array;

    /** Wyszukiwanie opcji dla panelu. @return list<array{id:int,label:string,meta:?string}> */
    abstract public function search(array $field, string $term, int $limit = 20): array;
}
