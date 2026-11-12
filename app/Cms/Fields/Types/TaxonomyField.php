<?php

namespace App\Cms\Fields\Types;

use App\Cms\Taxonomy\Term;
use Illuminate\Database\Eloquent\Builder;

class TaxonomyField extends RelationFieldBase
{
    public function name(): string
    {
        return 'taxonomy';
    }

    public function label(): string
    {
        return 'Taksonomia';
    }

    protected function multiple(array $field): bool
    {
        return ($field['field_type'] ?? 'multi_select') !== 'select';
    }

    protected function defaults(): array
    {
        // field_type: select (jeden termin) | multi_select (wiele terminów)
        return [...parent::defaults(), 'taxonomy' => 'category', 'field_type' => 'multi_select'];
    }

    protected function query(array $field): Builder
    {
        return Term::query()->whereHas('taxonomy', fn ($q) => $q->where('key', $field['taxonomy']));
    }

    public function labels(array $field, array $ids): array
    {
        return Term::whereIn('id', $ids)->get()->mapWithKeys(fn (Term $t) => [$t->id => ['id' => $t->id, 'label' => $t->name, 'meta' => null]])->all();
    }

    public function search(array $field, string $term, int $limit = 20): array
    {
        return $this->query($field)
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (Term $t) => ['id' => $t->id, 'label' => $t->name, 'meta' => null])->all();
    }
}
