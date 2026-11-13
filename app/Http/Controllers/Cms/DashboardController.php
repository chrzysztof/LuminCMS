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

        // Aktywność z ostatnich 6 miesięcy: nowe treści i publikacje, zliczane w PHP (niezależnie od bazy danych).
        $from = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(fn ($i) => $from->copy()->addMonths($i));
        $rows = $contents()->where(fn ($q) => $q->where('created_at', '>=', $from)->orWhere('published_at', '>=', $from))
            ->limit(5000)->get(['status', 'created_at', 'published_at']);
        $count = fn ($rows, string $col, $m) => $rows->filter(fn ($c) => $c->{$col}?->isSameMonth($m))->count();

        return Inertia::render('dashboard', [
            'activity' => $months->map(fn ($m) => [
                'month' => $m->format('Y-m'),
                'created' => $count($rows, 'created_at', $m),
                'published' => $rows->where('status', Content::STATUS_PUBLISHED)->filter(fn ($c) => $c->published_at?->isSameMonth($m))->count(),
            ])->values(),
            'new_30d' => $contents()->where('created_at', '>=', now()->subDays(30))->count(),
            'quick' => [
                'pages' => $user->can('edit_pages'),
                'posts' => $user->can('edit_posts'),
                'media' => $user->can('upload_files'),
                'customize' => $user->can('manage_options'),
            ],
            'stats' => [
                'published' => $contents()->where('status', Content::STATUS_PUBLISHED)->count(),
                'drafts' => $contents()->whereIn('status', [Content::STATUS_DRAFT, Content::STATUS_PENDING])->count(),
                'scheduled' => $contents()->where('status', Content::STATUS_SCHEDULED)->count(),
                'media' => $user->can('upload_files')
                    ? Media::query()->when(! $user->can('edit_others_posts'), fn ($q) => $q->where('uploaded_by', $user->id))->count()
                    : null,
            ],
            'recent' => $contents()->with(['type:id,key,label', 'author:id,name'])->latest('updated_at')->limit(8)->get()->map(fn (Content $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'type_key' => $c->type->key,
                'type_label' => $c->type->label,
                'author' => $c->author?->name,
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
