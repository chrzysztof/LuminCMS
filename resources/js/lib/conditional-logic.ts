import type { ConditionalRule, FieldDefinition } from '@/types/cms';

type Values = Record<string, unknown>;

/** Jak w PHP: wartości jako teksty, bool zamieniany na "1" / "0", puste wartości pomijane. */
const asList = (v: unknown): string[] =>
    (Array.isArray(v) ? v : [v])
        .filter((item) => item !== null && item !== undefined && item !== '')
        .map((item) =>
            typeof item === 'boolean'
                ? item
                    ? '1'
                    : '0'
                : String(item as string | number),
        );

/** Jak w PHP: null, pusty tekst, pusta lista i false to "puste". */
const isEmpty = (v: unknown): boolean =>
    v === null ||
    v === undefined ||
    v === '' ||
    v === false ||
    (Array.isArray(v) && v.length === 0);

function ruleMatches(rule: ConditionalRule, values: Values): boolean {
    const list = asList(values[rule.field]);
    const expected = rule.value ?? '';

    switch (rule.operator) {
        case '==':
            return list.includes(expected);
        case '!=':
            return !list.includes(expected);
        case '==empty':
            return isEmpty(values[rule.field]);
        case '!=empty':
            return !isEmpty(values[rule.field]);
        case '==pattern':
            try {
                return list.some((v) => new RegExp(expected).test(v));
            } catch {
                return false;
            }
        case '==contains':
            return list.some((v) => v.includes(expected));
        default:
            return true;
    }
}

/** Lustro ConditionalLogic z PHP: OR między grupami, AND w grupie. Serwer i tak waliduje ponownie. */
export function isVisible(field: FieldDefinition, values: Values): boolean {
    const groups = field.conditional_logic;

    if (!groups || groups.length === 0) {
        return true;
    }

    return groups.some((group) =>
        group.every((rule) => ruleMatches(rule, values)),
    );
}
