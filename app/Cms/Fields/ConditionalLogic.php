<?php

namespace App\Cms\Fields;

/**
 * Logika warunkowa pola: lista grup (OR), każda grupa to lista warunków (AND).
 * Warunek: ['field' => 'nazwa_pola', 'operator' => '==', 'value' => 'x'].
 * Operatory: ==, !=, ==empty, !=empty, ==pattern, ==contains.
 */
class ConditionalLogic
{
    public function passes(array $field, array $values): bool
    {
        $groups = $field['conditional_logic'] ?? false;

        if (! is_array($groups) || $groups === []) {
            return true;
        }

        foreach ($groups as $conditions) {
            if ($this->allPass($conditions, $values)) {
                return true;
            }
        }

        return false;
    }

    private function allPass(array $conditions, array $values): bool
    {
        foreach ($conditions as $condition) {
            if (! $this->conditionPasses($condition, $values)) {
                return false;
            }
        }

        return true;
    }

    private function conditionPasses(array $c, array $values): bool
    {
        $actual = $values[$c['field'] ?? ''] ?? null;
        $expected = (string) ($c['value'] ?? '');

        return match ($c['operator'] ?? '==') {
            '==' => $this->equals($actual, $expected),
            '!=' => ! $this->equals($actual, $expected),
            '==empty' => $this->isEmpty($actual),
            '!=empty' => ! $this->isEmpty($actual),
            '==pattern' => $this->matchesPattern($actual, $expected),
            '==contains' => $this->contains($actual, $expected),
            default => false,
        };
    }

    /** Wartość (lub elementy tablicy) jako teksty; bool zamieniany na "1" / "0". */
    private function strings(mixed $actual): array
    {
        $items = is_array($actual) ? $actual : [$actual];

        return array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) $v, $items);
    }

    private function equals(mixed $actual, string $expected): bool
    {
        return in_array($expected, $this->strings($actual), true);
    }

    private function isEmpty(mixed $actual): bool
    {
        return $actual === null || $actual === '' || $actual === [] || $actual === false;
    }

    private function matchesPattern(mixed $actual, string $pattern): bool
    {
        $regex = '/'.str_replace('/', '\/', $pattern).'/u';

        foreach ($this->strings($actual) as $item) {
            if (@preg_match($regex, $item) === 1) {
                return true;
            }
        }

        return false;
    }

    private function contains(mixed $actual, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        foreach ($this->strings($actual) as $item) {
            if (str_contains($item, $needle)) {
                return true;
            }
        }

        return false;
    }
}
