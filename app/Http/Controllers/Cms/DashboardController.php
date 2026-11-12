<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Media\Media;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        // Użytkownik bez edit_others_* widzi statystyki wyłącznie własnych treści.
        $scope = fn (Builder $q) => $q->when(
            ! $user->can('edit_others_posts') && ! $user->can('edit_others_pages'),
            fn (Builder $q) => $q->where('author_id', $user->id),
        );

        $contents = fn () => $scope(Content::query()->whereHas('type', fn ($t) => $t->whereIn('capability_type', $this->editable($user))));

        return Inertia::render('dashboard', [
            'stats' => [
                'published' => $contents()->where('status', Content::STATUS_PUBLISHED)->count(),
                'drafts' => $contents()->whereIn('status', [Content::STATUS_DRAFT, Content::STATUS_PENDING])->count(),
                'scheduled' => $contents()->where('status', Content::STATUS_SCHEDULED)->count(),
                'media' => $user->can('upload_files')
                    ? Media::query()->when(! $user->can('edit_others_posts'), fn ($q) => $q->where('uploaded_by', $user->id))->count()
                    : null,
            ],
            'recent' => $contents()->with('type:id,key,label')->latest('updated_at')->limit(8)->get()->map(fn (Content $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'type_key' => $c->type->key,
                'type_label' => $c->type->label,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    /** @return list<string> zestawy uprawnień treści ("post"/"page"), które użytkownik może edytować */
    private function editable($user): array
    {
        return array_values(array_filter(['post', 'page'], fn ($t) => $user->can("edit_{$t}s")));
    }
}
