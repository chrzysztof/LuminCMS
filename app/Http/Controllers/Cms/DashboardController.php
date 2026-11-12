<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Media\Media;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'published' => Content::where('status', Content::STATUS_PUBLISHED)->count(),
                'drafts' => Content::where('status', Content::STATUS_DRAFT)->count(),
                'scheduled' => Content::where('status', Content::STATUS_SCHEDULED)->count(),
                'media' => Media::count(),
            ],
            'recent' => Content::with('type:id,key,label')->latest('updated_at')->limit(8)->get()->map(fn (Content $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'type_key' => $c->type->key,
                'type_label' => $c->type->label,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]),
        ]);
    }
}
