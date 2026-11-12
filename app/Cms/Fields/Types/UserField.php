<?php

namespace App\Cms\Fields\Types;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserField extends RelationFieldBase
{
    public function name(): string
    {
        return 'user';
    }

    public function label(): string
    {
        return 'Użytkownik';
    }

    protected function multiple(array $field): bool
    {
        return (bool) ($field['multiple'] ?? false);
    }

    protected function defaults(): array
    {
        return [...parent::defaults(), 'multiple' => false, 'allow_null' => true];
    }

    protected function query(array $field): Builder
    {
        return User::query();
    }

    /** Publicznie zwracamy tylko bezpieczne dane użytkownika (bez adresu e-mail i haszy). */
    public function format(mixed $value, array $field): mixed
    {
        $result = parent::format($value, $field);

        if ($field['return_format'] === 'id' || $result === null) {
            return $result;
        }

        $simplify = fn (User $u) => ['id' => $u->id, 'name' => $u->name];

        return $this->multiple($field) ? array_map($simplify, $result) : $simplify($result);
    }

    public function labels(array $field, array $ids): array
    {
        return User::whereIn('id', $ids)->get()->mapWithKeys(fn (User $u) => [$u->id => ['id' => $u->id, 'label' => $u->name, 'meta' => null]])->all();
    }

    public function search(array $field, string $term, int $limit = 20): array
    {
        return User::query()
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name, 'meta' => null])->all();
    }
}
