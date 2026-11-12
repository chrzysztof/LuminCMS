<?php

namespace App\Cms\Fields;

use App\Cms\Content\Content;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class FieldGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'location_rules' => 'array',
            'hide_on_screen' => 'array',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('menu_order');
    }

    /** Aktywne grupy pól, których reguły lokalizacji pasują do kontekstu. */
    public static function forContext(array $context): Collection
    {
        $matcher = app(LocationMatcher::class);

        return static::active()->get()
            ->filter(fn (self $group) => $matcher->matches($group->location_rules ?? [], $context))
            ->values();
    }

    public static function forContent(Content $content): Collection
    {
        return static::forContext([
            'content_type' => $content->type->key,
            'template' => $content->template ?: 'default',
            'parent' => $content->parent_id,
            'status' => $content->status,
            'term' => $content->terms()->pluck('terms.id')->all(),
        ]);
    }
}
