'use no memo';

import type { Editor } from 'grapesjs';
import { Eye } from 'lucide-react';
import { useEffect, useReducer } from 'react';
import type { CustomField } from '@/components/cms/builder/field-binding';
import { t } from '@/lib/i18n';

type When = '' | 'logged_in' | 'logged_out' | 'filled' | 'empty' | 'period';

const WHEN_LABELS: Record<Exclude<When, ''>, string> = {
    logged_in: 'Tylko dla zalogowanych',
    logged_out: 'Tylko dla niezalogowanych',
    filled: 'Gdy pole jest wypełnione',
    empty: 'Gdy pole jest puste',
    period: 'W wybranym okresie',
};

const ATTRS = [
    'data-lm-show',
    'data-lm-show-field',
    'data-lm-show-from',
    'data-lm-show-to',
];

const inputClass =
    'border-input bg-transparent flex w-full rounded-md border px-2 py-1.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

/** Warunek wyświetlania zaznaczonego elementu; sprawdzany na stronie przy każdym wyświetleniu (shortcode [show]). */
export function DisplayConditions({
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

    if (!editor || !c || c.is('wrapper')) return null;

    const attrs = c.getAttributes();
    const when = String(attrs['data-lm-show'] ?? '') as When;
    const field = String(attrs['data-lm-show-field'] ?? '');
    const from = String(attrs['data-lm-show-from'] ?? '');
    const to = String(attrs['data-lm-show-to'] ?? '');

    const set = (patch: Record<string, string>) => {
        const next = { ...attrs, ...patch };
        const clean: Record<string, string> = {};
        ATTRS.forEach((key) => {
            if (next[key]) clean[key] = String(next[key]);
        });
        c.removeAttributes(ATTRS);
        if (clean['data-lm-show']) c.addAttributes(clean);
        onChange();
        refresh();
    };

    return (
        <div className="mb-3 rounded-md border p-2">
            <p className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <Eye className="size-3.5" /> {t('Warunek wyświetlania')}
            </p>
            <div className="space-y-1.5">
                <select
                    className={inputClass}
                    value={when}
                    aria-label={t('Warunek wyświetlania')}
                    onChange={(e) =>
                        set({
                            'data-lm-show': e.target.value,
                            'data-lm-show-field': '',
                            'data-lm-show-from': '',
                            'data-lm-show-to': '',
                        })
                    }
                >
                    <option value="">{t('Zawsze')}</option>
                    {(Object.keys(WHEN_LABELS) as Exclude<When, ''>[]).map(
                        (key) => (
                            <option key={key} value={key}>
                                {t(WHEN_LABELS[key])}
                            </option>
                        ),
                    )}
                </select>
                {(when === 'filled' || when === 'empty') && (
                    <select
                        className={inputClass}
                        value={field}
                        aria-label={t('Pole własne')}
                        onChange={(e) =>
                            set({ 'data-lm-show-field': e.target.value })
                        }
                    >
                        <option value="">{t('Wybierz pole…')}</option>
                        {fields.map((f) => (
                            <option key={f.name} value={f.name}>
                                {f.label}
                            </option>
                        ))}
                    </select>
                )}
                {when === 'period' && (
                    <div className="grid grid-cols-2 gap-1.5">
                        <label className="text-xs text-muted-foreground">
                            {t('Od')}
                            <input
                                type="date"
                                className={inputClass}
                                value={from}
                                onChange={(e) =>
                                    set({ 'data-lm-show-from': e.target.value })
                                }
                            />
                        </label>
                        <label className="text-xs text-muted-foreground">
                            {t('Do')}
                            <input
                                type="date"
                                className={inputClass}
                                value={to}
                                onChange={(e) =>
                                    set({ 'data-lm-show-to': e.target.value })
                                }
                            />
                        </label>
                    </div>
                )}
                {when !== '' && (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'W edytorze element jest zawsze widoczny; warunek działa na stronie.',
                        )}
                    </p>
                )}
            </div>
        </div>
    );
}
