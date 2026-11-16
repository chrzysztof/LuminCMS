<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\BuilderRenderer;
use App\Cms\Builder\ContentRevision;
use App\Cms\Builder\RevisionRecorder;
use App\Cms\Builder\SavedBlock;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\ContentAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuilderController extends Controller
{
    private const MAX_PROJECT_BYTES = 2_000_000;

    public function __construct(private readonly BuilderRenderer $renderer, private readonly ContentAccess $access, private readonly BlockRegistry $blocks, private readonly RevisionRecorder $revisions) {}

    public function edit(Request $request, ContentType $type, Content $content): Response
    {
        $this->authorizeBuilder($request, $type, $content);

        return Inertia::render('content/builder', [
            'type' => ['key' => $type->key, 'label' => $type->label, 'label_plural' => $type->label_plural],
            'content' => [
                'id' => $content->id,
                'title' => $content->title,
                'status' => $content->status,
                'slug' => $content->slug,
            ],
            'project' => $content->builder_data,
            'themeBlocks' => $this->blocks->definitions(),
            'savedBlocks' => SavedBlock::orderBy('name')->get(['id', 'name', 'html', 'user_id'])->all(),
            'recovery' => $this->recovery($content),
        ]);
    }

    public function save(Request $request, ContentType $type, Content $content): JsonResponse
    {
        $this->authorizeBuilder($request, $type, $content);

        $data = $request->validate([
            'project' => ['required', 'array'],
            'status' => ['sometimes', Rule::in([Content::STATUS_DRAFT, Content::STATUS_PUBLISHED])],
        ]);

        abort_if(strlen(json_encode($data['project'])) > self::MAX_PROJECT_BYTES, 413, __('Projekt jest za duży.'));

        $content->builder_data = $data['project'];
        $content->html_cache = $this->renderer->render($data['project']);

        // Bez uprawnienia publish_* builder zapisuje tylko wersję roboczą / do recenzji.
        if (isset($data['status']) && $data['status'] === Content::STATUS_PUBLISHED && ! $this->access->canPublish($request->user(), $type)) {
            $data['status'] = Content::STATUS_PENDING;
        }

        if (isset($data['status'])) {
            $content->status = $data['status'];

            if ($data['status'] === Content::STATUS_PUBLISHED) {
                $content->published_at ??= now();
            }
        }

        $content->save();

        $this->revisions->record(
            $content,
            $data['project'],
            $content->status === Content::STATUS_PUBLISHED && ($data['status'] ?? null) === Content::STATUS_PUBLISHED ? ContentRevision::KIND_PUBLISH : ContentRevision::KIND_SAVE,
            $request->user()->id,
        );

        return response()->json([
            'saved_at' => $content->updated_at?->toIso8601String(),
            'status' => $content->status,
            'html_bytes' => strlen($content->html_cache),
        ]);
    }

    /** Jeśli ostatnia wersja w historii to autozapis, po ostatnim zapisie zostały niezapisane zmiany do odzyskania. */
    private function recovery(Content $content): ?array
    {
        $last = ContentRevision::where('content_id', $content->id)->latest('id')->first(['id', 'kind', 'created_at']);

        return $last?->kind === ContentRevision::KIND_AUTOSAVE
            ? ['id' => $last->id, 'created_at' => $last->created_at->toIso8601String()]
            : null;
    }

    /** Autozapis: odkłada wersję w historii, nie zmienia treści ani strony na żywo. */
    public function autosave(Request $request, ContentType $type, Content $content): JsonResponse
    {
        $this->authorizeBuilder($request, $type, $content);

        $data = $request->validate(['project' => ['required', 'array']]);

        abort_if(strlen(json_encode($data['project'])) > self::MAX_PROJECT_BYTES, 413, __('Projekt jest za duży.'));

        $revision = $this->revisions->record($content, $data['project'], ContentRevision::KIND_AUTOSAVE, $request->user()->id);

        return response()->json(['saved_at' => now()->toIso8601String(), 'stored' => $revision !== null]);
    }

    public function revisions(Request $request, ContentType $type, Content $content): JsonResponse
    {
        $this->authorizeBuilder($request, $type, $content);

        return response()->json(
            ContentRevision::where('content_id', $content->id)->with('user:id,name')->latest('id')->limit(80)
                ->get(['id', 'user_id', 'kind', 'created_at'])
                ->map(fn (ContentRevision $r) => [
                    'id' => $r->id,
                    'kind' => $r->kind,
                    'author' => $r->user?->name,
                    'created_at' => $r->created_at->toIso8601String(),
                ])->all(),
        );
    }

    public function revision(Request $request, ContentType $type, Content $content, int $revision): JsonResponse
    {
        $this->authorizeBuilder($request, $type, $content);

        $row = ContentRevision::where('content_id', $content->id)->findOrFail($revision);

        return response()->json(['project' => json_decode($row->builder_data, true)]);
    }

    private function authorizeBuilder(Request $request, ContentType $type, Content $content): void
    {
        abort_unless($content->content_type_id === $type->id, 404);
        abort_unless($type->supports('builder'), 404);
        abort_unless($this->access->canEdit($request->user(), $content->setRelation('type', $type)), 403);
    }
}
