<?php

namespace App\Cms\Builder;

use App\Cms\Content\Content;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    public const KIND_SAVE = 'save';

    public const KIND_PUBLISH = 'publish';

    public const KIND_AUTOSAVE = 'autosave';

    protected $table = 'content_revisions';

    protected $guarded = [];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
