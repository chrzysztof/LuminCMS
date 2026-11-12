<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Media\Media;
use App\Cms\Media\MediaManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MediaController extends Controller
{
    public function __construct(private readonly MediaManager $manager) {}

    public function index(Request $request): JsonResponse
    {
        $query = Media::query()->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($q) => $q->where('filename', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")->orWhere('alt', 'like', "%{$search}%"));
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('mime', 'like', $type === 'image' ? 'image/%' : ($type === 'other' ? 'application/%' : "{$type}/%"));
        }

        $page = $query->paginate(min(max($request->integer('per_page', 40), 1), 100));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $m) => $m->asArray('medium'))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('media.max_upload_kb'), 'mimetypes:'.implode(',', config('media.allowed_mimes'))],
            'alt' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $media = $this->manager->store(
            $request->file('file'),
            $request->only(['alt', 'title', 'caption']),
            $request->user()?->id,
        );

        return response()->json($media->asArray(), 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'focal_x' => ['sometimes', 'numeric', 'between:0,1'],
            'focal_y' => ['sometimes', 'numeric', 'between:0,1'],
        ]);

        $media->fill(array_map(fn ($v) => $v ?? '', array_intersect_key($data, array_flip(['alt', 'title']))))
            ->fill(array_intersect_key($data, array_flip(['caption'])))
            ->save();

        if (isset($data['focal_x']) || isset($data['focal_y'])) {
            $this->manager->setFocalPoint($media, $data['focal_x'] ?? $media->focal_x, $data['focal_y'] ?? $media->focal_y);
        }

        return response()->json($media->refresh()->asArray());
    }

    public function crop(Request $request, Media $media, string $size): JsonResponse
    {
        $rect = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->manager->setCrop($media, $size, $rect);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['crop' => $e->getMessage()]);
        }

        return response()->json($media->refresh()->asArray());
    }

    public function destroyCrop(Media $media, string $size): JsonResponse
    {
        $this->manager->clearCrop($media, $size);

        return response()->json($media->refresh()->asArray());
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->manager->delete($media);

        return response()->json(null, 204);
    }
}
