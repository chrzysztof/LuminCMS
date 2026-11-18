import type { Component, Editor } from 'grapesjs';
import { t } from '@/lib/i18n';

type Option = { id: string; name: string; cls?: string };
type ClassGroup = { name: string; label: string; options: Option[] };

const variant: ClassGroup = {
    name: 'variant',
    label: t('Styl przycisku'),
    options: [
        { id: '', name: t('Wypełniony') },
        { id: 'secondary', name: t('Obrys'), cls: 'lm-btn-secondary' },
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
                this.set(group.name, current ? current.id : '', {
                    silent: true,
                });
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

    dc.addType('lm-section', {
        isComponent: (el) =>
            el.tagName === 'SECTION' && el.classList?.contains('lm-section')
                ? { type: 'lm-section' }
                : false,
        model: {
            defaults: { ...base, name: t('Sekcja'), traits: [] },
        },
    });

    dc.addType('lm-grid', {
        isComponent: (el) =>
            el.tagName === 'DIV' && el.classList?.contains('lm-grid')
                ? { type: 'lm-grid' }
                : false,
        model: {
            defaults: { ...base, name: t('Kolumny'), traits: [] },
        },
    });

    dc.addType('lm-card', {
        isComponent: (el) =>
            el.tagName === 'DIV' && el.classList?.contains('lm-card')
                ? { type: 'lm-card' }
                : false,
        model: { defaults: { ...base, name: t('Karta'), traits: [] } },
    });

    dc.addType('lm-heading', {
        extend: 'text',
        isComponent: (el) =>
            /^H[1-6]$/.test(el.tagName ?? '') ? { type: 'lm-heading' } : false,
        model: {
            defaults: {
                ...base,
                name: t('Nagłówek'),
                traits: [
                    {
                        type: 'select',
                        name: 'level',
                        label: t('Poziom nagłówka'),
                        changeProp: true,
                        options: [1, 2, 3, 4, 5, 6].map((n) => ({
                            id: `h${n}`,
                            label: `H${n}`,
                        })),
                    },
                ],
            },
            init(this: Component) {
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
            defaults: { ...base, name: t('Akapit'), traits: [] },
        },
    });

    // Tekst sformatowany: treść edytuje się w panelu właściwości (edytor jak we wpisie), a na płótnie jest tylko podgląd.
    // HTML trzymamy we właściwości richHtml; serwer czyści go tym samym filtrem co treść wpisów.
    dc.addType('lm-richtext', {
        isComponent: (el) =>
            el.getAttribute?.('data-lm-richtext') !== null &&
            el.getAttribute?.('data-lm-richtext') !== undefined
                ? { type: 'lm-richtext' }
                : false,
        model: {
            defaults: {
                ...base,
                name: t('Akapit'),
                tagName: 'div',
                classes: ['lm-prose'],
                droppable: false,
                editable: false,
                components: [],
                richHtml: '',
                traits: [],
            },
        },
        view: {
            init() {
                this.listenTo(this.model, 'change:richHtml', () =>
                    this.render(),
                );
            },
            onRender() {
                this.el.innerHTML = String(
                    this.model.get('richHtml') ||
                        `<p>${t('Kliknij i wpisz treść we właściwościach.')}</p>`,
                );
            },
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
                name: t('Przycisk'),
                tagName: 'a',
                traits: [
                    { type: 'text', name: 'href', label: t('Adres (URL)') },
                    {
                        type: 'select',
                        name: 'target',
                        label: t('Otwórz'),
                        options: [
                            { id: '', label: t('W tym samym oknie') },
                            { id: '_blank', label: t('W nowej karcie') },
                        ],
                    },
                    ...button.traits,
                ],
            },
            init(this: Component) {
                button.init.call(this);
            },
        },
    });

    dc.addType('lm-image', {
        extend: 'image',
        isComponent: (el) =>
            el.tagName === 'IMG' ? { type: 'lm-image' } : false,
        model: {
            defaults: {
                ...base,
                name: t('Obraz'),
                droppable: false,
                traits: [
                    {
                        type: 'text',
                        name: 'alt',
                        label: t('Tekst alternatywny (ALT)'),
                        placeholder: t('Z biblioteki, jeśli puste'),
                    },
                    {
                        type: 'select',
                        name: 'data-size',
                        label: t('Rozmiar'),
                        options: [
                            { id: 'thumbnail', label: t('Miniatura') },
                            { id: 'medium', label: t('Średni') },
                            { id: 'large', label: t('Duży') },
                            { id: 'full', label: t('Pełny') },
                        ],
                    },
                ],
            },
        },
    });
}
