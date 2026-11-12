<?php

namespace App\Cms\Taxonomy;

use App\Cms\Content\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    /** Rodzic istnieje tylko w taksonomiach hierarchicznych. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'content_term');
    }
}
