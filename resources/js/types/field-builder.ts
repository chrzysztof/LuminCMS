import type { ConditionalRule } from '@/types/cms';

export type FieldTypeInfo = {
    name: string;
    label: string;
    category: string;
    stores_data: boolean;
    /** Ustawienia typu z wartościami domyślnymi (bez ustawień wspólnych). */
    settings: Record<string, unknown>;
};

export type FieldDraft = {
    /** Klucz roboczy interfejsu (nie jest zapisywany). */
    _id: string;
    type: string;
    name: string;
    label: string;
    instructions: string;
    required: boolean;
    conditional_logic: false | ConditionalRule[][];
    wrapper: { width: string; class: string; id: string };
    [setting: string]: unknown;
};

export type LocationRule = {
    param: string;
    operator: '==' | '!=';
    value: string;
};

export type GroupGeneral = {
    title: string;
    position: 'after_title' | 'normal' | 'side';
    style: 'default' | 'seamless';
    label_placement: 'top' | 'left';
    instruction_placement: 'label' | 'field';
    active: boolean;
};
