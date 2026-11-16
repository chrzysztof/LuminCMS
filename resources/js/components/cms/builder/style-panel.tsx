'use no memo';

import type { Component, Editor } from 'grapesjs';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Monitor,
    Smartphone,
    Tablet,
} from 'lucide-react';
import { useEffect, useReducer } from 'react';
import spec from '@/lib/builder/utilities.json';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type Device = 'Desktop' | 'Tablet' | 'Mobile';

/** Prefiks klas dla urządzenia: komputer = bazowe, tablet = lm-md-, telefon = lm-sm-. */
const PREFIX: Record<Device, string> = {
    Desktop: '',
    Tablet: 'md-',
    Mobile: 'sm-',
};
const DEVICE_LABEL: Record<Device, string> = {
    Desktop: t('komputera'),
    Tablet: t('tabletu'),
    Mobile: t('telefonu'),
};

type Option = { value: string; label: string; icon?: React.ReactNode };
type Group = {
    id: string;
    label: string;
    responsive: boolean;
    control: 'select' | 'segment';
    options: Option[];
    /** Nazwa klasy (bez `lm-` i prefiksu urządzenia) dla wartości. */
    stem: (value: string) => string;
};

const steps = (map: Record<string, string>): Option[] =>
    Object.entries(map).map(([value, rem]) => ({
        value,
        label: rem === '0' ? '0' : rem.replace('rem', ' rem'),
    }));

const spacing = (id: string, label: string): Group => ({
    id,
    label,
    responsive: true,
    control: 'select',
    options: steps(spec.spacing),
    stem: (v) => `${id}-${v}`,
});

const GROUPS: Record<string, Group> = {
    pt: spacing('pt', t('Odstęp wewnętrzny: góra')),
    pb: spacing('pb', t('Odstęp wewnętrzny: dół')),
    mt: spacing('mt', t('Margines: góra')),
    mb: spacing('mb', t('Margines: dół')),
    gap: {
        id: 'gap',
        label: t('Odstęp między kolumnami'),
        responsive: true,
        control: 'select',
        options: steps(spec.gap),
        stem: (v) => `gap-${v}`,
    },
    size: {
        id: 'size',
        label: t('Rozmiar tekstu'),
        responsive: true,
        control: 'select',
        options: [
            { value: 'sm', label: t('Mały') },
            { value: 'lg', label: t('Duży') },
            { value: 'xl', label: t('Większy') },
            { value: '2xl', label: t('Największy') },
        ],
        stem: (v) => `text-${v}`,
    },
    align: {
        id: 'align',
        label: t('Wyrównanie tekstu'),
        responsive: true,
        control: 'segment',
        options: [
            { value: 'left', label: t('Do lewej'), icon: <AlignLeft /> },
            { value: 'center', label: t('Do środka'), icon: <AlignCenter /> },
            { value: 'right', label: t('Do prawej'), icon: <AlignRight /> },
        ],
        stem: (v) => v,
    },
    cols: {
        id: 'cols',
        label: t('Liczba kolumn'),
        responsive: true,
        control: 'segment',
        options: [1, 2, 3, 4].map((n) => ({
            value: String(n),
            label: String(n),
        })),
        stem: (v) => `cols-${v}`,
    },
    valign: {
        id: 'valign',
        label: t('Wyrównanie w pionie'),
        responsive: false,
        control: 'segment',
        options: [
            { value: 'start', label: t('Do góry') },
            { value: 'center', label: t('Do środka') },
        ],
        stem: (v) => `items-${v}`,
    },
    bg: {
        id: 'bg',
        label: t('Tło'),
        responsive: false,
        control: 'select',
        options: [
            { value: 'muted', label: t('Jasnoszare') },
            { value: 'brand', label: t('Kolor marki') },
            { value: 'dark', label: t('Ciemne') },
        ],
        stem: (v) => `bg-${v}`,
    },
};

type Toggle = { cls: string; label: string };
const LOOK: Toggle[] = [
    { cls: 'lm-rounded', label: t('Zaokrąglone rogi') },
    { cls: 'lm-shadow', label: t('Cień') },
    { cls: 'lm-border', label: t('Obramowanie') },
];
const HIDE: Toggle[] = [
    { cls: 'lm-hide-desktop', label: t('Ukryj na komputerze') },
    { cls: 'lm-hide-tablet', label: t('Ukryj na tablecie') },
    { cls: 'lm-hide-mobile', label: t('Ukryj na telefonie') },
];

/** Jakie ustawienia mają sens dla danego typu elementu. */
const CAPS: Record<string, string[]> = {
    'lm-section': ['pt', 'pb', 'mt', 'mb', 'align', 'size', 'bg'],
    'lm-grid': ['cols', 'gap', 'valign', 'mt', 'mb'],
    'lm-card': ['pt', 'pb', 'mt', 'mb', 'align', 'look'],
    'lm-heading': ['align', 'size', 'mt', 'mb'],
    'lm-paragraph': ['align', 'size', 'mt', 'mb'],
    'lm-button': ['mt', 'mb'],
    'lm-image': ['look', 'mt', 'mb'],
    'lm-block': ['mt', 'mb'],
};
const DEFAULT_CAPS = ['pt', 'pb', 'mt', 'mb', 'align'];

const className = (group: Group, device: Device, value: string) =>
    `lm-${group.responsive ? PREFIX[device] : ''}${group.stem(value)}`;

function own(component: Component, group: Group, device: Device): string {
    const classes = component.getClasses() as string[];

    return (
        group.options.find((o) =>
            classes.includes(className(group, device, o.value)),
        )?.value ?? ''
    );
}

/** Wartość odziedziczona z większego ekranu (podejście desktop-first). */
function inherited(component: Component, group: Group, device: Device): string {
    const chain: Device[] =
        device === 'Mobile'
            ? ['Tablet', 'Desktop']
            : device === 'Tablet'
              ? ['Desktop']
              : [];

    for (const d of chain) {
        const v = own(component, group, d);
        if (v) return v;
    }

    return '';
}

function setValue(
    component: Component,
    group: Group,
    device: Device,
    value: string,
) {
    group.options.forEach((o) =>
        component.removeClass(className(group, device, o.value)),
    );

    if (value) component.addClass(className(group, device, value));
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="mb-3">
            <p className="mb-1 text-xs text-muted-foreground">{label}</p>
            {children}
        </div>
    );
}

function GroupControl({
    component,
    group,
    device,
    onChange,
}: {
    component: Component;
    group: Group;
    device: Device;
    onChange: () => void;
}) {
    const current = own(component, group, device);
    const fallback = group.responsive
        ? inherited(component, group, device)
        : '';
    const apply = (value: string) => {
        setValue(component, group, device, value);
        onChange();
    };

    if (group.control === 'segment') {
        return (
            <Field label={group.label}>
                <div className="flex gap-1 rounded-lg bg-muted p-0.5">
                    {group.options.map((o) => {
                        const active = current === o.value;
                        const ghost = !current && fallback === o.value;

                        return (
                            <button
                                key={o.value}
                                type="button"
                                title={o.label}
                                aria-label={o.label}
                                aria-pressed={active}
                                onClick={() => apply(active ? '' : o.value)}
                                className={cn(
                                    'flex h-8 flex-1 items-center justify-center rounded-md text-sm [&_svg]:size-4',
                                    active && 'bg-white font-medium shadow-sm',
                                    ghost &&
                                        'bg-white/50 text-muted-foreground',
                                    !active && !ghost && 'hover:bg-white/60',
                                )}
                            >
                                {o.icon ?? o.label}
                            </button>
                        );
                    })}
                </div>
            </Field>
        );
    }

    const fallbackLabel = group.options.find(
        (o) => o.value === fallback,
    )?.label;

    return (
        <Field label={group.label}>
            <select
                value={current}
                onChange={(e) => apply(e.target.value)}
                className="h-8 w-full rounded-md border bg-white px-2 text-sm"
            >
                <option value="">
                    {fallbackLabel
                        ? t('Dziedziczone: {value}', { value: fallbackLabel })
                        : t('Domyślnie')}
                </option>
                {group.options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
        </Field>
    );
}

function Toggles({
    component,
    items,
    onChange,
}: {
    component: Component;
    items: Toggle[];
    onChange: () => void;
}) {
    return (
        <div className="mb-3 space-y-1.5">
            {items.map(({ cls, label }) => (
                <label key={cls} className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={(component.getClasses() as string[]).includes(
                            cls,
                        )}
                        onChange={(e) => {
                            if (e.target.checked) component.addClass(cls);
                            else component.removeClass(cls);
                            onChange();
                        }}
                    />
                    {label}
                </label>
            ))}
        </div>
    );
}

function Heading({ children }: { children: React.ReactNode }) {
    return (
        <p className="mt-4 mb-2 border-t pt-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {children}
        </p>
    );
}

type Props = {
    editor: Editor | null;
    device: Device;
    /** Wywoływane po każdej zmianie, żeby oznaczyć projekt jako niezapisany. */
    onChange: () => void;
};

/**
 * Panel stylu: zamiast edytora CSS wybiera klasy narzędziowe z jednej skali
 * (odstępy, wyrównanie, kolumny...), osobno dla komputera, tabletu i telefonu.
 * Wynikowy HTML zawiera tylko klasy `lm-*`, bez stylów inline.
 */
export function StylePanel({ editor, device, onChange }: Props) {
    const [, rerender] = useReducer((n: number) => n + 1, 0);

    useEffect(() => {
        if (!editor) return;

        const events =
            'component:selected component:deselected component:update:classes';
        editor.on(events, rerender);

        return () => {
            editor.off(events, rerender);
        };
    }, [editor]);

    const component = editor?.getSelected();

    if (!editor || !component || component.is('wrapper')) return null;

    const type = String(component.get('type') ?? '');
    const caps = [...(CAPS[type] ?? DEFAULT_CAPS)];
    const isContainer = (component.getClasses() as string[]).includes(
        'lm-container',
    );
    const has = (...ids: string[]) => ids.some((id) => caps.includes(id));
    const changed = () => {
        onChange();
        rerender();
    };
    const DeviceIcon =
        device === 'Desktop'
            ? Monitor
            : device === 'Tablet'
              ? Tablet
              : Smartphone;

    const render = (ids: string[]) =>
        ids
            .filter((id) => caps.includes(id))
            .map((id) => (
                <GroupControl
                    key={`${id}-${device}`}
                    component={component}
                    group={GROUPS[id]}
                    device={device}
                    onChange={changed}
                />
            ));

    return (
        <div className="mt-2">
            <div className="mb-3 flex items-start gap-2 rounded-lg bg-brand-soft p-2 text-xs text-brand-foreground">
                <DeviceIcon className="mt-0.5 size-4 shrink-0" />
                <span>
                    {t('Edytujesz ustawienia dla {device}.', {
                        device: DEVICE_LABEL[device],
                    })}{' '}
                    {device === 'Desktop'
                        ? t(
                              'Obowiązują też na mniejszych ekranach, dopóki ich tam nie zmienisz.',
                          )
                        : t('Zmiana nie wpływa na większe ekrany.')}
                </span>
            </div>

            {has('cols', 'gap', 'valign') && (
                <>
                    <Heading>{t('Układ')}</Heading>
                    {render(['cols', 'gap', 'valign'])}
                </>
            )}
            {isContainer && (
                <>
                    <Heading>{t('Układ')}</Heading>
                    <Toggles
                        component={component}
                        items={[
                            {
                                cls: 'lm-narrow',
                                label: t('Wąska szerokość treści'),
                            },
                        ]}
                        onChange={changed}
                    />
                </>
            )}
            {has('pt', 'pb', 'mt', 'mb') && (
                <>
                    <Heading>{t('Odstępy')}</Heading>
                    {render(['pt', 'pb', 'mt', 'mb'])}
                </>
            )}
            {has('align', 'size') && (
                <>
                    <Heading>{t('Tekst')}</Heading>
                    {render(['align', 'size'])}
                </>
            )}
            {has('bg', 'look') && (
                <>
                    <Heading>{t('Wygląd')}</Heading>
                    {render(['bg'])}
                    {caps.includes('look') && (
                        <Toggles
                            component={component}
                            items={LOOK}
                            onChange={changed}
                        />
                    )}
                </>
            )}
            <Heading>{t('Widoczność')}</Heading>
            <Toggles component={component} items={HIDE} onChange={changed} />
        </div>
    );
}
