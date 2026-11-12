<?php

namespace App\Cms\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Zdefiniowany rozmiar obrazu (jak add_image_size w WordPressie). */
class MediaSize extends Model
{
    protected $table = 'media_sizes';

    /** Nazwa zarezerwowana dla głównego wariantu WebP w pełnym rozmiarze. */
    public const FULL = 'full';

    protected $fillable = ['name', 'label', 'width', 'height', 'crop', 'active', 'is_system'];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'crop' => 'boolean',
            'active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
