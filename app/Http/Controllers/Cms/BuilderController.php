<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Builder\BuilderRenderer;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuilderController extends Controller
{
    private const MAX_PROJECT_BYTES = 2_000_000;

    public function __construct(private readonly BuilderRenderer $renderer) {}

    public function edit(ContentType $type, Content $content): Response
    {
        $this->authorizeBuilder($type, $content);

        return Inertia::render('content/builder', [
            'type' => ['key' => $type->key, 'label' => $type->label, 'label_plural' => $type->label_plural],
            'content' => [
                'id' => $content->id,
                'title' => $content->title,
                'status' => $content->status,
                'slug' => $content->slug,
            ],
            'project' => $content->builder_data,
        ]);
    }

    public function save(Request $request, ContentType $type, Content $content): JsonResponse
    {
        $this->authorizeBuilder($type, $content);

        $data = $request->validate([
            'project' => ['required', 'array'],
            'status' => ['sometimes', Rule::in([Content::STATUS_DRAFT, Content::STATUS_PUBLISHED])],
        ]);

        abort_if(strlen(json_encode($data['project'])) > self::MAX_PROJECT_BYTES, 413, 'Projekt jest za duży.');

        $content->builder_data = $data['project'];
        $content->html_cache = $this->renderer->render($data['project']);

        if (isset($data['status'])) {
            $content->status = $data['status'];

            if ($data['status'] === Content::STATUS_PUBLISHED) {
                $content->published_at ??= now();
            }
        }

        $content->save();

        return response()->json([
            'saved_at' => $content->updated_at?->toIso8601String(),
            'status' => $content->status,
            'html_bytes' => strlen($content->html_cache),
        ]);
    }

    private function authorizeBuilder(ContentType $type, Content $content): void
    {
        abort_unless($content->content_type_id === $type->id, 404);
        abort_unless($type->supports('builder'), 404);
    }
}
