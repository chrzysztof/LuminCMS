<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\Types\RelationFieldBase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opcje i etykiety dla pól relacji (Post Object, Relationship, Taxonomy, User).
 * Pole wskazujemy grupą i ścieżką nazw (np. "slajdy.powiazany"), więc ograniczenia
 * pobieramy z zapisanej definicji, a nie z danych przesłanych przez przeglądarkę.
 */
class RelationController extends Controller
{
    public function __construct(private readonly FieldRegistry $registry) {}

    public function __invoke(Request $request, FieldGroup $group): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:100'],
            'ids' => ['nullable', 'string', 'max:2000'],
        ]);

        $field = $this->find($group->fields ?? [], explode('.', $data['field']));
        $type = $field ? $this->registry->get($field['type']) : null;

        abort_unless($type instanceof RelationFieldBase, 404);

        if (! empty($data['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $data['ids']))));

            return response()->json(['items' => array_values($type->labels($field, $ids))]);
        }

        return response()->json(['items' => $type->search($field, $data['q'] ?? '')]);
    }

    /** @param  list<string>  $path */
    private function find(array $definitions, array $path): ?array
    {
        $name = array_shift($path);

        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! $this->registry->has($definition['type'] ?? '')) {
                continue;
            }

            $field = $this->registry->resolveField($definition);

            if ($field['name'] !== $name) {
                continue;
            }

            if ($path === []) {
                return $field;
            }

            $type = $this->registry->get($field['type']);

            return $type instanceof ContainerFieldType ? $this->find($type->subFields($field), $path) : null;
        }

        return null;
    }
}
