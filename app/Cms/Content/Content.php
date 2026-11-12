<?php

namespace App\Cms\Content;

use App\Cms\Fields\FieldRegistry;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    /** Oczekuje na przegląd (jak w WordPressie: współpracownik nie może publikować). */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_SCHEDULED = 'scheduled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'seo' => 'array',
            'builder_data' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContentType::class, 'content_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('menu_order');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'content_term');
    }

    public function scopePublished(Builder $query): Builder
    {
        // Opublikowane oraz zaplanowane, którym minął termin publikacji.
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $p) => $p->where('status', self::STATUS_PUBLISHED)
                ->where(fn (Builder $d) => $d->whereNull('published_at')->orWhere('published_at', '<=', now())))
            ->orWhere(fn (Builder $s) => $s->where('status', self::STATUS_SCHEDULED)->where('published_at', '<=', now())));
    }

    /**
     * Wartość pola własnego sformatowana według jego ustawień (return_format).
     * Zwraca $default, gdy grupa pól nie definiuje takiego pola.
     */
    public function field(string $name, mixed $default = null): mixed
    {
        return app(FieldRegistry::class)->valueFor($this, $name, $default);
    }
}
