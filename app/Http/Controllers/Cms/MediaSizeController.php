<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Media\Media;
use App\Cms\Media\MediaSize;
use App\Http\Controllers\Controller;
use App\Jobs\RegenerateMediaVariants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaSizeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => MediaSize::orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'not_in:'.MediaSize::FULL, Rule::unique('media_sizes', 'name')],
            'label' => ['required', 'string', 'max:100'],
            ...$this->dimensionRules(),
        ]);

        $this->assertCropHasBothSides($data);

        return response()->json(MediaSize::create($data + ['is_system' => false]), 201);
    }

    public function update(Request $request, MediaSize $size): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            ...$this->dimensionRules(sometimes: true),
        ]);

        $this->assertCropHasBothSides($data + $size->only(['width', 'height', 'crop']));

        $size->update($data);

        return response()->json($size->refresh());
    }

    public function destroy(MediaSize $size): JsonResponse
    {
        abort_if($size->is_system, 422, 'Rozmiaru systemowego nie można usunąć (można go wyłączyć).');

        $size->delete();

        return response()->json(null, 204);
    }

    /** Kolejkuje odtworzenie wariantów wszystkich obrazów po zmianie rozmiarów. */
    public function regenerate(): JsonResponse
    {
        $ids = Media::where('mime', 'like', 'image/%')->pluck('id');
        $ids->each(fn ($id) => RegenerateMediaVariants::dispatch($id));

        return response()->json(['queued' => $ids->count()], 202);
    }

    private function dimensionRules(bool $sometimes = false): array
    {
        $required = $sometimes ? 'sometimes' : 'required';

        return [
            'width' => [$required, 'integer', 'min:0', 'max:10000'],
            'height' => [$required, 'integer', 'min:0', 'max:10000'],
            'crop' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    private function assertCropHasBothSides(array $data): void
    {
        if (! empty($data['crop']) && (($data['width'] ?? 0) < 1 || ($data['height'] ?? 0) < 1)) {
            abort(422, 'Kadrowany rozmiar wymaga szerokości i wysokości.');
        }

        if (($data['width'] ?? 0) < 1 && ($data['height'] ?? 0) < 1) {
            abort(422, 'Podaj szerokość lub wysokość.');
        }
    }
}
