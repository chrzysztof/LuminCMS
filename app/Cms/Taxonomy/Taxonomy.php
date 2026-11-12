<?php

namespace App\Cms\Taxonomy;

use App\Cms\Content\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hierarchical' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function contentTypes(): BelongsToMany
    {
        return $this->belongsToMany(ContentType::class, 'taxonomy_content_type');
    }
}
