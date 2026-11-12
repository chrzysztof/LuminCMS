<?php

namespace App\Cms\Fields\Types;

use App\Cms\Content\Content;
use Illuminate\Database\Eloquent\Builder;

class PostObjectField extends RelationFieldBase
{
    public function name(): string
    {
        return 'post_object';
    }

    public function label(): string
    {
        return 'Treść (pojedyncza)';
    }

    protected function multiple(array $field): bool
    {
        return (bool) ($field['multiple'] ?? false);
    }

    protected function defaults(): array
    {
        return [...parent::defaults(), 'post_type' => [], 'post_status' => ['published'], 'multiple' => false, 'allow_null' => true];
    }

    protected function query(array $field): Builder
    {
        $query = Content::query();

        if ($types = array_filter((array) $field['post_type'])) {
            $query->whereHas('type', fn ($q) => $q->whereIn('key', $types));
        }

        if ($statuses = array_filter((array) $field['post_status'])) {
            $query->whereIn('status', $statuses);
        }

        return $query;
    }

    public function labels(array $field, array $ids): array
    {
        return Content::with('type:id,label')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (Content $c) => [$c->id => ['id' => $c->id, 'label' => $c->title, 'meta' => $c->type?->label]])->all();
    }

    public function search(array $field, string $term, int $limit = 20): array
    {
        return $this->query($field)->with('type:id,label')
            ->when($term !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('title')->limit($limit)->get()
            ->map(fn (Content $c) => ['id' => $c->id, 'label' => $c->title, 'meta' => $c->type?->label])->all();
    }
}
