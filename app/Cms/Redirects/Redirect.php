<?php

namespace App\Cms\Redirects;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    public const CODES = [301, 302, 307, 308, 410];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'auto' => 'boolean', 'last_hit_at' => 'datetime'];
    }
}
