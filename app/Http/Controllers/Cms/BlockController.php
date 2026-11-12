<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Media\Media;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Podgląd bloków motywu w edytorze: markup z szablonu bloku i obiekty mediów do pól obrazów. */
class BlockController extends Controller
{
    public function __construct(private readonly BlockRegistry $blocks, private readonly FieldRegistry $registry) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'block' => ['required', 'string', 'max:60'],
            'attrs' => ['nullable', 'array'],
        ]);

        $block = $this->blocks->get($data['block']);
        abort_unless($block, 404);

        $attrs = $this->blocks->normalize($data['block'], (array) ($data['attrs'] ?? []));
        $ids = array_values(array_unique(array_filter($this->mediaIds($block['fields'], $attrs), 'is_numeric')));

        return response()->json([
            'html' => $this->blocks->render($data['block'], $attrs),
            'attrs' => (object) $attrs,
            'media' => (object) Media::whereIn('id', $ids)->get()->mapWithKeys(fn (Media $m) => [$m->id => $m->asArray('medium')])->all(),
        ]);
    }

    /** @return list<mixed> */
    private function mediaIds(array $fields, array $values): array
    {
        $ids = [];

        foreach ($fields as $field) {
            $value = $values[$field['name'] ?? ''] ?? null;

            if (in_array($field['type'], ['image', 'file', 'gallery'], true)) {
                $ids = [...$ids, ...(is_array($value) ? $value : [$value])];
            } elseif (($type = $this->registry->get($field['type'])) instanceof ContainerFieldType) {
                foreach ($type->entries($field, $value) as $entry) {
                    $sub = array_map(fn ($f) => $this->registry->resolveField($f), $entry['fields']);
                    $ids = [...$ids, ...$this->mediaIds($sub, $entry['row'])];
                }
            }
        }

        return $ids;
    }
}
