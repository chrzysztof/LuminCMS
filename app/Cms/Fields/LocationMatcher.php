<?php

namespace App\Cms\Fields;

/**
 * Reguły lokalizacji grupy pól: lista grup (OR), w grupie reguły połączone AND.
 * Reguła: ['param' => 'content_type', 'operator' => '==' | '!=', 'value' => 'page'].
 * Obsługiwane parametry kontekstu: content_type, template, parent, status,
 * term (identyfikatory terminów treści), taxonomy (edycja terminu), user_role, options_page.
 */
class LocationMatcher
{
    public function matches(array $ruleGroups, array $context): bool
    {
        foreach ($ruleGroups as $rules) {
            if ($rules !== [] && $this->allMatch($rules, $context)) {
                return true;
            }
        }

        return false;
    }

    private function allMatch(array $rules, array $context): bool
    {
        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule, $context)) {
                return false;
            }
        }

        return true;
    }

    private function ruleMatches(array $rule, array $context): bool
    {
        $actual = $context[$rule['param'] ?? ''] ?? null;
        $expected = (string) ($rule['value'] ?? '');

        $found = is_array($actual)
            ? in_array($expected, array_map('strval', $actual), true)
            : (string) $actual === $expected;

        return ($rule['operator'] ?? '==') === '!=' ? ! $found : $found;
    }
}
