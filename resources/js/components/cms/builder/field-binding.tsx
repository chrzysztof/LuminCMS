'use no memo';

import type { Component, Editor } from 'grapesjs';
import { Database } from 'lucide-react';
import { useEffect, useReducer } from 'react';
import { t } from '@/lib/i18n';

export type CustomField = {
    name: string;
    label: string;
    type: string;
    preview: string;
};

type Mode = 'text' | 'html' | 'href' | 'src';

const HTML_TAGS = [
    'div',
    'section',
    'article',
    'aside',
    'blockquote',
    'li',
    'td',
    'figcaption',
];
const URL_TYPES = ['url', 'link', 'page_link', 'file', 'image', 'email'];

const nativeClass =
    'border-input bg-transparent flex w-full rounded-md border px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

/** Sposoby powiązania zależne od elementu: obraz bierze adres z pola obrazu, link adres albo tekst, reszta tekst. */
function modesFor(c: Component): Mode[] {
    const tag = String(c.get('tagName') ?? '').toLowerCase();

    if (c.is('lm-image') || tag === 'img') return ['src'];
    if (tag === 'a' || c.is('lm-button') || c.is('link'))
        return ['text', 'href'];
    if (HTML_TAGS.includes(tag)) return ['text', 'html'];

    return ['text'];
}

const modeLabel: Record<Mode, string> = {
    text: 'Tekst',
    html: 'Treść HTML (np. z edytora)',
    href: 'Adres odnośnika',
    src: 'Obraz',
};

/** Powiązanie zaznaczonego elementu z polem własnym tej treści; wartość pojawia się na stronie przy wyświetlaniu. */
export function FieldBinding({
    editor,
    fields,
    onChange,
}: {
    editor: Editor | null;
    fields: CustomField[];
    onChange: () => void;
}) {
    const [, refresh] = useReducer((n: number) => n + 1, 0);

    useEffect(() => {
        if (!editor) return;
        const events =
            'component:selected component:deselected component:update:attributes';
        editor.on(events, refresh);

        return () => {
            editor.off(events, refresh);
        };
    }, [editor]);

    const c = editor?.getSelected();

    if (
        !editor ||
        !c ||
        c.is('wrapper') ||
        c.is('lm-block') ||
        c.is('lm-richtext')
    )
        return null;

    const attrs = c.getAttributes();
    const name = String(attrs['data-lm-field'] ?? '');
    const modes = modesFor(c);
    const mode = (String(attrs['data-lm-bind'] ?? '') as Mode) || modes[0];
    const activeMode = modes.includes(mode) ? mode : modes[0];
    const usable = fields.filter((f) =>
        activeMode === 'src'
            ? f.type === 'image'
            : activeMode === 'href'
              ? URL_TYPES.includes(f.type)
              : true,
    );
    const current = fields.find((f) => f.name === name);

    const apply = (field: string, bind: Mode) => {
        if (field === '') {
            // Wybrany sposób zostaje zapamiętany, ale bez pola renderer niczego nie podmienia.
            c.removeAttributes(['data-lm-field']);
            c.addAttributes({ 'data-lm-bind': bind });
        } else {
            c.addAttributes({
                'data-lm-field': field,
                'data-lm-bind': bind,
            });
        }
        onChange();
        refresh();
    };

    return (
        <div className="mb-3 rounded-md border p-2">
            <p className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <Database className="size-3.5" /> {t('Pole własne')}
            </p>
            {fields.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Ta treść nie ma pól własnych. Dodaj grupę pól w menu „Pola własne”.',
                    )}
                </p>
            ) : (
                <div className="space-y-1.5">
                    {modes.length > 1 && (
                        <select
                            className={nativeClass}
                            value={activeMode}
                            onChange={(e) =>
                                apply(name, e.target.value as Mode)
                            }
                            aria-label={t('Co zastąpić')}
                        >
                            {modes.map((m) => (
                                <option key={m} value={m}>
                                    {t(modeLabel[m])}
                                </option>
                            ))}
                        </select>
                    )}
                    <select
                        className={nativeClass}
                        value={name}
                        onChange={(e) => apply(e.target.value, activeMode)}
                        aria-label={t('Pole własne')}
                    >
                        <option value="">{t('Bez powiązania')}</option>
                        {usable.map((f) => (
                            <option key={f.name} value={f.name}>
                                {f.label}
                            </option>
                        ))}
                        {name && !usable.some((f) => f.name === name) && (
                            <option value={name}>{name}</option>
                        )}
                    </select>
                    {name && (
                        <p className="text-xs text-muted-foreground">
                            {current?.preview
                                ? `${t('Teraz')}: ${current.preview}`
                                : t(
                                      'Wartość pojawi się na stronie. Tekst w edytorze to wartość zastępcza.',
                                  )}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
