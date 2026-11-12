<?php

namespace App\Cms\Menus;

use App\Cms\Content\Content;
use App\Cms\Taxonomy\Term;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    public const TYPES = ['content', 'term', 'custom', 'heading'];

    public const LAYOUTS = ['container', 'full'];

    /** Maksymalna liczba poziomów (megamenu: pozycja, kolumna, odnośnik, odnośnik podrzędny). */
    public const MAX_DEPTH = 4;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['new_tab' => 'boolean', 'mega' => 'boolean'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function megaPart(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'mega_part_id');
    }
}
