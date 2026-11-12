import type { FieldDraft } from '@/types/field-builder';

let counter = 0;
export const uid = () => `f${Date.now().toString(36)}${counter++}`;

/** Definicja pola z serwera → stan edytora (z kluczem roboczym `_id`, także dla pól podrzędnych). */
export function toDraft(raw: Record<string, unknown>): FieldDraft {
    const draft = {
        label: '',
        name: '',
        instructions: '',
        required: false,
        conditional_logic: false,
        ...raw,
        wrapper: {
            width: '',
            class: '',
            id: '',
            ...((raw.wrapper as object) ?? {}),
        },
        _id: uid(),
    } as FieldDraft;

    if (Array.isArray(raw.sub_fields)) {
        draft.sub_fields = (raw.sub_fields as Record<string, unknown>[]).map(
            toDraft,
        );
    }

    if (Array.isArray(raw.layouts)) {
        draft.layouts = (raw.layouts as Record<string, unknown>[]).map((l) => ({
            min: '',
            max: '',
            ...l,
            _id: uid(),
            sub_fields: Array.isArray(l.sub_fields)
                ? (l.sub_fields as Record<string, unknown>[]).map(toDraft)
                : [],
        }));
    }

    return draft;
}
