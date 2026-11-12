import type { Component, Editor } from 'grapesjs';
import { api } from '@/lib/api';
import { t } from '@/lib/i18n';
import type { FieldDefinition } from '@/types/cms';

/** Blok zdefiniowany przez motyw (themes/{slug}/blocks/{nazwa}/block.json). */
export type ThemeBlock = {
    name: string;
    label: string;
    category: string;
    icon: string;
    fields: FieldDefinition[];
    defaults: Record<string, unknown>;
};

const svg = (inner: string) =>
    `<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`;

const fallbackIcon = svg(
    '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>',
);

const escapeHtml = (s: string) =>
    s.replace(/[&<>"]/g, (c) => `&#${c.charCodeAt(0)};`);

const placeholder = (label: string) =>
    `<div style="padding:1.25rem;border:1px dashed #9a9aa5;border-radius:.5rem;text-align:center;font:14px system-ui,sans-serif;color:#62626c">${escapeHtml(label)}</div>`;

// Podgląd z serwera jest deterministyczny dla (blok, wartości), więc zapamiętujemy go na czas sesji edytora.
const previews = new Map<string, string>();

/** Rejestruje komponent "lm-block" i bloki motywu w panelu bloków edytora. */
export function registerThemeBlocks(editor: Editor, blocks: ThemeBlock[]) {
    if (blocks.length === 0) return;

    const byName = new Map(blocks.map((b) => [b.name, b]));

    editor.DomComponents.addType('lm-block', {
        isComponent: () => false,
        model: {
            defaults: {
                name: t('Blok motywu'),
                block: '',
                attrs: {},
                droppable: false,
                stylable: false,
                traits: [],
                // Markup powstaje na serwerze ([block]), więc samo HTML komponentu jest puste.
                void: false,
            },
            init(this: Component) {
                const def = byName.get(String(this.get('block') ?? ''));
                if (def) this.set('name', def.label, { silent: true });
            },
        },
        view: {
            onRender(this: {
                el: HTMLElement;
                model: Component;
                timer?: number;
            }) {
                const refresh = () => {
                    const name = String(this.model.get('block') ?? '');
                    const attrs = (this.model.get('attrs') ?? {}) as Record<
                        string,
                        unknown
                    >;
                    const def = byName.get(name);
                    const key = `${name}|${JSON.stringify(attrs)}`;

                    if (!def) {
                        this.el.innerHTML = placeholder(
                            t('Blok niedostępny w tym motywie'),
                        );
                        return;
                    }

                    const cached = previews.get(key);
                    if (cached !== undefined) {
                        this.el.innerHTML = cached || placeholder(def.label);
                        return;
                    }

                    this.el.innerHTML = placeholder(def.label);
                    api<{ html: string }>('/cms/blocks/preview', {
                        method: 'POST',
                        body: { block: name, attrs },
                    })
                        .then(({ html }) => {
                            previews.set(key, html);
                            this.el.innerHTML = html || placeholder(def.label);
                        })
                        .catch(() => undefined);
                };

                refresh();
                this.model.on('change:attrs', () => {
                    window.clearTimeout(this.timer);
                    this.timer = window.setTimeout(refresh, 200);
                });
            },
        } as never,
    });

    blocks.forEach((b) =>
        editor.Blocks.add(`theme-${b.name}`, {
            label: b.label,
            category: b.category ? t(b.category) : t('Motyw'),
            media: b.icon ? svg(b.icon) : fallbackIcon,
            select: true,
            content: { type: 'lm-block', block: b.name, attrs: b.defaults },
        }),
    );
}
