<?php

namespace App\Cms\Content;

use App\Cms\Taxonomy\Taxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hierarchical' => 'boolean',
            'is_system' => 'boolean',
            'supports' => 'array',
        ];
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'taxonomy_content_type');
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports ?? [], true);
    }
}
