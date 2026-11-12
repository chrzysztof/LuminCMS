<?php

namespace App\Cms\Redirects;

use Illuminate\Database\Eloquent\Model;

class NotFoundLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
