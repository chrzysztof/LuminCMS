'use no memo';

import type { Component, Editor } from 'grapesjs';
import { AlertTriangle, ImagePlus, X } from 'lucide-react';
import { useEffect, useReducer, useState } from 'react';
import { MediaPicker } from '@/components/cms/media-picker';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { MediaItem } from '@/types/cms';

const inputClass =
    'border-input bg-transparent flex h-8 w-full min-w-0 rounded-md border px-2 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

/** Wartość zapisywana na elemencie (reguła CSS dla bieżącego urządzenia); pusta usuwa właściwość. */
function setProp(c: Component, prop: string, value: string) {
    const style = { ...(c.getStyle() as Record<string, string>) };

    if (value === '') delete style[prop];
    else style[prop] = value;

    c.setStyle(style);
}

const getStyle = (c: Component) => c.getStyle() as Record<string, string>;

/** Pole tekstowe zatwierdzane po opuszczeniu pola lub Enterem (jeden wpis w historii zamiast jednego na literę). */
function TextStyle({
    component,
    prop,
    placeholder,
    onChange,
}: {
    component: Component;
    prop: string;
    placeholder?: string;
    onChange: () => void;
}) {
    const stored = getStyle(component)[prop] ?? '';
    const [draft, setDraft] = useState<string | null>(null);
    const value = draft ?? stored;

    const commit = () => {
        if (draft !== null && draft !== stored) {
            setProp(component, prop, draft.trim());
            onChange();
        }
        setDraft(null);
    };

    return (
        <input
            className={inputClass}
            value={value}
            placeholder={placeholder}
            onChange={(e) => setDraft(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
        />
    );
}

function ColorStyle({
    component,
    prop,
    label,
    onChange,
}: {
    component: Component;
    prop: string;
    label: string;
    onChange: () => void;
}) {
    const value = getStyle(component)[prop] ?? '';
    const isHex = /^#[0-9a-f]{6}$/i.test(value);

    return (
        <div className="mb-3">
            <p className="mb-1 text-xs text-muted-foreground">{label}</p>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    aria-label={label}
                    className="h-8 w-10 shrink-0 cursor-pointer rounded-md border bg-transparent p-0.5"
                    value={isHex ? value : '#ffffff'}
                    onChange={(e) => {
                        setProp(component, prop, e.target.value);
                        onChange();
                    }}
                />
                <TextStyle
                    component={component}
                    prop={prop}
                    placeholder="#rrggbb"
                    onChange={onChange}
                />
                {value !== '' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0"
                        aria-label={t('Usuń')}
                        onClick={() => {
                            setProp(component, prop, '');
                            onChange();
                        }}
                    >
                        <X />
                    </Button>
                )}
            </div>
        </div>
    );
}

function SidesStyle({
    component,
    group,
    label,
    onChange,
}: {
    component: Component;
    group: 'padding' | 'margin';
    label: string;
    onChange: () => void;
}) {
    const sides = [
        ['top', t('Góra')],
        ['right', t('Prawo')],
        ['bottom', t('Dół')],
        ['left', t('Lewo')],
    ] as const;

    return (
        <div className="mb-3">
            <p className="mb-1 text-xs text-muted-foreground">{label}</p>
            <div className="grid grid-cols-4 gap-1.5">
                {sides.map(([side, name]) => (
                    <label
                        key={side}
                        className="text-[11px] text-muted-foreground"
                    >
                        {name}
                        <TextStyle
                            component={component}
                            prop={`${group}-${side}`}
                            placeholder="0"
                            onChange={onChange}
                        />
                    </label>
                ))}
            </div>
        </div>
    );
}

/** Własne style zaznaczonego elementu (dla bieżącego urządzenia). Trafiają do osobnego CSS-a jako klasa lm-x-*, a nie do atrybutu style. */
export function LocalStylesPanel({
    editor,
    deviceLabel,
    onChange,
}: {
    editor: Editor | null;
    deviceLabel: string;
    onChange: () => void;
}) {
    const [, refresh] = useReducer((n: number) => n + 1, 0);
    const [picking, setPicking] = useState(false);

    useEffect(() => {
        if (!editor) return;
        const events =
            'component:selected component:deselected component:styleUpdate device:select';
        editor.on(events, refresh);

        return () => {
            editor.off(events, refresh);
        };
    }, [editor]);

    const c = editor?.getSelected();

    if (!editor || !c || c.is('wrapper') || c.is('lm-block')) return null;

    const style = getStyle(c);
    const count = Object.keys(style).length;
    const image = style['background-image'] ?? '';

    const pickImage = (items: MediaItem[]) => {
        const media = items[0];
        if (!media) return;
        // Adres względny (sama ścieżka), żeby strona działała po zmianie domeny.
        let path = media.url;
        try {
            const u = new URL(media.url, window.location.origin);
            if (u.origin === window.location.origin) path = u.pathname;
        } catch {
            /* zostaje adres z biblioteki */
        }
        const next = { ...style };
        next['background-image'] = `url('${path}')`;
        next['background-size'] = 'cover';
        next['background-position'] = 'center';
        c.setStyle(next);
        onChange();
    };

    return (
        <details
            className="mb-3 rounded-md border p-3"
            open={count > 0 || undefined}
        >
            <summary className="cursor-pointer text-sm font-medium">
                {t('Własne style (zaawansowane)')}
                {count > 0 && (
                    <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                        ({count})
                    </span>
                )}
            </summary>
            <div className="mt-3">
                <div className="mb-3 flex gap-2 rounded-md bg-amber-50 p-2 text-xs text-amber-900">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <p>
                        {t(
                            'Własne style mogą spowolnić ładowanie strony i sprawić, że wygląd będzie niespójny z motywem. Jeśli to możliwe, używaj gotowych opcji powyżej.',
                        )}
                    </p>
                </div>
                <p className="mb-3 text-xs text-muted-foreground">
                    {t('Dotyczy urządzenia: {name}', { name: deviceLabel })}
                </p>

                <ColorStyle
                    component={c}
                    prop="background-color"
                    label={t('Kolor tła')}
                    onChange={onChange}
                />
                <ColorStyle
                    component={c}
                    prop="color"
                    label={t('Kolor tekstu')}
                    onChange={onChange}
                />

                <div className="mb-3">
                    <p className="mb-1 text-xs text-muted-foreground">
                        {t('Obraz w tle')}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="flex-1"
                            onClick={() => setPicking(true)}
                        >
                            <ImagePlus />{' '}
                            {image ? t('Zmień obraz') : t('Wybierz obraz')}
                        </Button>
                        {image && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={t('Usuń obraz w tle')}
                                onClick={() => {
                                    const next = { ...style };
                                    delete next['background-image'];
                                    delete next['background-size'];
                                    delete next['background-position'];
                                    c.setStyle(next);
                                    onChange();
                                }}
                            >
                                <X />
                            </Button>
                        )}
                    </div>
                </div>

                <SidesStyle
                    component={c}
                    group="padding"
                    label={t('Odstęp wewnętrzny')}
                    onChange={onChange}
                />
                <SidesStyle
                    component={c}
                    group="margin"
                    label={t('Margines')}
                    onChange={onChange}
                />

                <div className="mb-3 grid grid-cols-3 gap-1.5">
                    {(
                        [
                            ['border-radius', t('Zaokrąglenie')],
                            ['max-width', t('Maks. szerokość')],
                            ['min-height', t('Min. wysokość')],
                        ] as const
                    ).map(([prop, name]) => (
                        <label
                            key={prop}
                            className="text-[11px] text-muted-foreground"
                        >
                            {name}
                            <TextStyle
                                component={c}
                                prop={prop}
                                onChange={onChange}
                            />
                        </label>
                    ))}
                </div>
                <p className="mb-2 text-[11px] text-muted-foreground">
                    {t('Wartości z jednostką, np. 1.5rem, 24px, 50%, auto.')}
                </p>

                {count > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={() => {
                            c.setStyle({});
                            onChange();
                        }}
                    >
                        {t('Wyczyść własne style')}
                    </Button>
                )}
            </div>

            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                imagesOnly
                onPick={pickImage}
            />
        </details>
    );
}
