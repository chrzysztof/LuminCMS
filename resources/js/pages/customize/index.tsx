import { Head, router } from '@inertiajs/react';
import { Loader2, Monitor, RotateCcw, Smartphone, Tablet } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Token = {
    key: string;
    group: string;
    label: string;
    type: 'color' | 'select' | 'size';
    css: string;
    default: string | number;
    options?: { value: string; label: string }[];
    min?: number;
    max?: number;
    step?: number;
    unit?: string;
};

type Values = Record<string, string | number>;

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const widths = { desktop: '100%', tablet: '768px', mobile: '390px' } as const;

// Blok :root z wartości różnych od domyślnych (ta sama zasada co po stronie serwera).
const buildCss = (tokens: Token[], values: Values) => {
    const lines = tokens
        .filter((tk) => values[tk.key] !== tk.default)
        .map(
            (tk) =>
                `${tk.css}:${values[tk.key]}${tk.type === 'size' ? (tk.unit ?? 'rem') : ''};`,
        );
    return lines.length ? `:root{${lines.join('')}}` : '';
};

export default function CustomizeIndex({
    theme,
    tokens,
    values: initial,
    previewUrl,
}: {
    theme: string;
    tokens: Token[];
    values: Values;
    previewUrl: string;
}) {
    const [values, setValues] = useState<Values>(initial);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [device, setDevice] = useState<keyof typeof widths>('desktop');
    const frame = useRef<HTMLIFrameElement>(null);

    const css = useMemo(() => buildCss(tokens, values), [tokens, values]);
    const dirty = JSON.stringify(values) !== JSON.stringify(initial);

    // Ramka jest z tej samej domeny, więc podmieniamy zawartość <style id="lm-theme-vars"> bez przeładowania.
    const apply = useCallback(() => {
        const el =
            frame.current?.contentDocument?.getElementById('lm-theme-vars');
        if (el) el.textContent = css;
    }, [css]);

    useEffect(apply, [apply]);

    const groups = useMemo(() => {
        const map = new Map<string, Token[]>();
        tokens.forEach((tk) =>
            map.set(tk.group, [...(map.get(tk.group) ?? []), tk]),
        );
        return [...map.entries()];
    }, [tokens]);

    const set = (key: string, value: string | number) =>
        setValues((v) => ({ ...v, [key]: value }));

    const save = () => {
        setSaving(true);
        router.put(
            cmsUrl.customize,
            { values },
            {
                preserveScroll: true,
                onSuccess: () => setErrors({}),
                onError: (e) => setErrors(e as Record<string, string>),
                onFinish: () => setSaving(false),
            },
        );
    };

    const reset = () => {
        if (
            confirm(t('Przywrócić wszystkie ustawienia wyglądu do domyślnych?'))
        )
            router.delete(cmsUrl.customize, {
                preserveScroll: true,
                onSuccess: () =>
                    setValues(
                        Object.fromEntries(
                            tokens.map((tk) => [tk.key, tk.default]),
                        ),
                    ),
            });
    };

    return (
        <>
            <Head title={t('Dostosuj')} />
            <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                <aside className="flex w-full shrink-0 flex-col border-b lg:w-80 lg:border-r lg:border-b-0">
                    <div className="border-b p-4">
                        <h1 className="text-lg font-semibold tracking-tight">
                            {t('Dostosuj')}
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            {t('Motyw')}: {theme}
                        </p>
                    </div>

                    <div className="flex-1 space-y-6 overflow-y-auto p-4 lg:max-h-[calc(100vh-13rem)]">
                        {groups.map(([group, list]) => (
                            <section key={group} className="space-y-3">
                                <h2 className="text-sm font-semibold">
                                    {t(group)}
                                </h2>
                                {list.map((tk) => (
                                    <div key={tk.key} className="grid gap-1.5">
                                        <div className="flex items-center justify-between gap-2">
                                            <Label htmlFor={`tk-${tk.key}`}>
                                                {t(tk.label)}
                                            </Label>
                                            {values[tk.key] !== tk.default && (
                                                <button
                                                    type="button"
                                                    className="text-muted-foreground hover:text-foreground"
                                                    aria-label={t(
                                                        'Przywróć domyślną wartość',
                                                    )}
                                                    title={t(
                                                        'Przywróć domyślną wartość',
                                                    )}
                                                    onClick={() =>
                                                        set(tk.key, tk.default)
                                                    }
                                                >
                                                    <RotateCcw className="size-3.5" />
                                                </button>
                                            )}
                                        </div>

                                        {tk.type === 'color' && (
                                            <div className="flex gap-2">
                                                <input
                                                    id={`tk-${tk.key}`}
                                                    type="color"
                                                    className="h-9 w-12 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                                    value={String(
                                                        values[tk.key],
                                                    )}
                                                    onChange={(e) =>
                                                        set(
                                                            tk.key,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <Input
                                                    value={String(
                                                        values[tk.key],
                                                    )}
                                                    maxLength={7}
                                                    className="font-mono"
                                                    aria-label={t(tk.label)}
                                                    onChange={(e) => {
                                                        const v =
                                                            e.target.value.trim();
                                                        if (
                                                            /^#[0-9a-fA-F]{0,6}$/.test(
                                                                v,
                                                            )
                                                        )
                                                            set(tk.key, v);
                                                    }}
                                                />
                                            </div>
                                        )}

                                        {tk.type === 'select' && (
                                            <select
                                                id={`tk-${tk.key}`}
                                                className={nativeClass}
                                                value={String(values[tk.key])}
                                                onChange={(e) =>
                                                    set(tk.key, e.target.value)
                                                }
                                            >
                                                {tk.options?.map((o) => (
                                                    <option
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {t(o.label)}
                                                    </option>
                                                ))}
                                            </select>
                                        )}

                                        {tk.type === 'size' && (
                                            <div className="flex items-center gap-3">
                                                <input
                                                    id={`tk-${tk.key}`}
                                                    type="range"
                                                    className="min-w-0 flex-1"
                                                    min={tk.min}
                                                    max={tk.max}
                                                    step={tk.step}
                                                    value={Number(
                                                        values[tk.key],
                                                    )}
                                                    onChange={(e) =>
                                                        set(
                                                            tk.key,
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <span className="w-16 text-right font-mono text-xs text-muted-foreground">
                                                    {Number(values[tk.key])}
                                                    {tk.unit}
                                                </span>
                                            </div>
                                        )}

                                        {errors[`values.${tk.key}`] && (
                                            <p className="text-xs text-destructive">
                                                {errors[`values.${tk.key}`]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </section>
                        ))}
                    </div>

                    <div className="flex items-center gap-2 border-t p-4">
                        <Button
                            type="button"
                            className="flex-1"
                            onClick={save}
                            disabled={saving || !dirty}
                        >
                            {saving && <Loader2 className="animate-spin" />}
                            {t('Zapisz')}
                        </Button>
                        <Button type="button" variant="outline" onClick={reset}>
                            {t('Przywróć domyślne')}
                        </Button>
                    </div>
                </aside>

                <div className="flex min-h-[28rem] flex-1 flex-col bg-muted/40">
                    <div className="flex justify-center gap-1 border-b p-2">
                        {(
                            [
                                ['desktop', Monitor, t('Komputer')],
                                ['tablet', Tablet, t('Tablet')],
                                ['mobile', Smartphone, t('Telefon')],
                            ] as const
                        ).map(([key, Icon, label]) => (
                            <Button
                                key={key}
                                type="button"
                                size="icon"
                                variant={device === key ? 'secondary' : 'ghost'}
                                aria-label={label}
                                aria-pressed={device === key}
                                onClick={() => setDevice(key)}
                            >
                                <Icon />
                            </Button>
                        ))}
                    </div>
                    <div className="flex flex-1 justify-center overflow-auto p-3">
                        <iframe
                            ref={frame}
                            src={previewUrl}
                            title={t('Podgląd witryny')}
                            onLoad={apply}
                            style={{ width: widths[device] }}
                            className={cn(
                                'h-full min-h-[26rem] max-w-full rounded-md border bg-white shadow-sm transition-[width]',
                            )}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
