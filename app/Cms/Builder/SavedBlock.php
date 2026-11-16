<?php

namespace App\Cms\Builder;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zapisana przez użytkownika sekcja edytora wizualnego (kopia, nie powiązanie). */
class SavedBlock extends Model
{
    protected $table = 'saved_blocks';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
