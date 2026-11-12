import type { Component, Editor } from 'grapesjs';

type Option = { id: string; name: string; cls?: string };
type ClassGroup = { name: string; label: string; options: Option[] };

const bg: ClassGroup = {
    name: 'bg',
    label: 'Tło',
    options: [
        { id: '', name: 'Białe' },
        { id: 'muted', name: 'Jasnoszare', cls: 'lm-bg-muted' },
        { id: 'brand', name: 'Kolor marki', cls: 'lm-bg-brand' },
        { id: 'dark', name: 'Ciemne', cls: 'lm-bg-dark' },
    ],
};

const pad: ClassGroup = {
    name: 'pad',
    label: 'Odstępy pionowe',
    options: [
        { id: 'sm', name: 'Małe', cls: 'lm-pad-sm' },
        { id: '', name: 'Normalne' },
        { id: 'lg', name: 'Duże', cls: 'lm-pad-lg' },
    ],
};

const align: ClassGroup = {
    name: 'align',
    label: 'Wyrównanie tekstu',
    options: [
        { id: '', name: 'Do lewej' },
        { id: 'center', name: 'Do środka', cls: 'lm-center' },
        { id: 'right', name: 'Do prawej', cls: 'lm-right' },
    ],
};

const cols: ClassGroup = {
    name: 'cols',
    label: 'Liczba kolumn',
    options: [
        { id: '2', name: '2', cls: 'lm-cols-2' },
        { id: '3', name: '3', cls: 'lm-cols-3' },
        { id: '4', name: '4', cls: 'lm-cols-4' },
    ],
};

const valign: ClassGroup = {
    name: 'valign',
    label: 'Wyrównanie w pionie',
    options: [
        { id: '', name: 'Do góry' },
        { id: 'center', name: 'Do środka', cls: 'lm-items-center' },
    ],
};

const variant: ClassGroup = {
    name: 'variant',
    label: 'Styl przycisku',
    options: [
        { id: '', name: 'Wypełniony' },
        { id: 'secondary', name: 'Obrys', cls: 'lm-btn-secondary' },
    ],
};

const selectTrait = (g: ClassGroup) => ({
    type: 'select',
    name: g.name,
    label: g.label,
    changeProp: true,
    options: g.options.map((o) => ({ id: o.id, label: o.name })),
});

/** Wspólna logika: właściwość komponentu przełącza klasy CSS z grupy (bez inline stylów). */
function classGroups(groups: ClassGroup[]) {
    const apply = (component: Component, group: ClassGroup) => {
        group.options.forEach((o) => o.cls && component.removeClass(o.cls));
        const chosen = group.options.find(
            (o) => o.id === component.get(group.name),
        );
        if (chosen?.cls) component.addClass(chosen.cls);
    };

    return {
        init(this: Component) {
            groups.forEach((group) => {
                const current = group.options.find(
                    (o) => o.cls && this.getClasses().includes(o.cls),
                );
                this.set(
                    group.name,
                    current ? current.id : group.name === 'cols' ? '2' : '',
                    { silent: true },
                );
                this.on(`change:${group.name}`, () => apply(this, group));
            });
        },
        traits: groups.map(selectTrait),
    };
}

const base = { resizable: false, copyable: true, stylable: false };

/** Własne typy komponentów: każdy z prostymi opcjami wyglądu zamiast edytora CSS. */
export function registerComponentTypes(editor: Editor) {
    const dc = editor.DomComponents;

    const section = classGroups([bg, pad, align]);
    dc.addType('lm-section', {
        isComponent: (el) =>
            el.tagName === 'SECTION' && el.classList?.contains('lm-section')
                ? { type: 'lm-section' }
                : false,
        model: {
            defaults: { ...base, name: 'Sekcja', traits: section.traits },
            init: section.init,
        },
    });

    const grid = classGroups([cols, valign]);
    dc.addType('lm-grid', {
        isComponent: (el) =>
            el.tagName === 'DIV' && el.classList?.contains('lm-grid')
                ? { type: 'lm-grid' }
                : false,
        model: {
            defaults: { ...base, name: 'Kolumny', traits: grid.traits },
            init: grid.init,
        },
    });

    dc.addType('lm-card', {
        isComponent: (el) =>
            el.tagName === 'DIV' && el.classList?.contains('lm-card')
                ? { type: 'lm-card' }
                : false,
        model: { defaults: { ...base, name: 'Karta' } },
    });

    const textAlign = classGroups([align]);
    dc.addType('lm-heading', {
        extend: 'text',
        isComponent: (el) =>
            /^H[1-6]$/.test(el.tagName ?? '') ? { type: 'lm-heading' } : false,
        model: {
            defaults: {
                ...base,
                name: 'Nagłówek',
                traits: [
                    {
                        type: 'select',
                        name: 'level',
                        label: 'Poziom nagłówka',
                        changeProp: true,
                        options: [1, 2, 3, 4, 5, 6].map((n) => ({
                            id: `h${n}`,
                            label: `H${n}`,
                        })),
                    },
                    ...textAlign.traits,
                ],
            },
            init(this: Component) {
                textAlign.init.call(this);
                this.set('level', String(this.get('tagName') ?? 'h2'), {
                    silent: true,
                });
                this.on('change:level', () =>
                    this.set('tagName', this.get('level')),
                );
            },
        },
    });

    dc.addType('lm-paragraph', {
        extend: 'text',
        isComponent: (el) =>
            el.tagName === 'P' ? { type: 'lm-paragraph' } : false,
        model: {
            defaults: { ...base, name: 'Akapit', traits: textAlign.traits },
            init: textAlign.init,
        },
    });

    const button = classGroups([variant]);
    dc.addType('lm-button', {
        extend: 'link',
        isComponent: (el) =>
            el.tagName === 'A' && el.classList?.contains('lm-btn')
                ? { type: 'lm-button', tagName: 'a' }
                : false,
        model: {
            defaults: {
                ...base,
                name: 'Przycisk',
                tagName: 'a',
                traits: [
                    { type: 'text', name: 'href', label: 'Adres (URL)' },
                    {
                        type: 'select',
                        name: 'target',
                        label: 'Otwórz',
                        options: [
                            { id: '', label: 'W tym samym oknie' },
                            { id: '_blank', label: 'W nowej karcie' },
                        ],
                    },
                    ...button.traits,
                ],
            },
            init: button.init,
        },
    });

    dc.addType('lm-image', {
        extend: 'image',
        isComponent: (el) =>
            el.tagName === 'IMG' ? { type: 'lm-image' } : false,
        model: {
            defaults: {
                ...base,
                name: 'Obraz',
                droppable: false,
                traits: [
                    {
                        type: 'text',
                        name: 'alt',
                        label: 'Tekst alternatywny (ALT)',
                        placeholder: 'Z biblioteki, jeśli puste',
                    },
                    {
                        type: 'select',
                        name: 'data-size',
                        label: 'Rozmiar',
                        options: [
                            { id: 'thumbnail', label: 'Miniatura' },
                            { id: 'medium', label: 'Średni' },
                            { id: 'large', label: 'Duży' },
                            { id: 'full', label: 'Pełny' },
                        ],
                    },
                ],
            },
        },
    });
}
