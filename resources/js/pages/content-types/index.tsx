import { Head, router } from '@inertiajs/react';
import { Boxes, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';
import { typeIcons } from '@/lib/type-icons';
import { cn } from '@/lib/utils';

type TypeRow = {
    key: string;
    label: string;
    label_plural: string;
    icon: string | null;
    hierarchical: boolean;
    supports: string[];
    url_prefix: string | null;
    capability_type: 'post' | 'page';
    is_system: boolean;
    contents_count: number;
    taxonomies: string[];
};

type Props = {
    types: TypeRow[];
    supportOptions: string[];
    taxonomyOptions: { key: string; label: string }[];
};

type Form = {
    key: string;
    label: string;
    label_plural: string;
    icon: string;
    hierarchical: boolean;
    supports: string[];
    url_prefix: string;
    capability_type: 'post' | 'page';
    taxonomies: string[];
};

const emptyForm: Form = {
    key: '',
    label: '',
    label_plural: '',
    icon: 'boxes',
    hierarchical: false,
    supports: ['title', 'editor', 'seo'],
    url_prefix: '',
    capability_type: 'post',
    taxonomies: [],
};

const supportLabels: Record<string, string> = {
    title: 'Tytuł',
    editor: 'Edytor tekstowy',
    builder: 'Edytor wizualny',
    excerpt: 'Zajawka',
    thumbnail: 'Obraz wyróżniający',
    seo: 'SEO',
    author: 'Autor',
    page_attributes: 'Atrybuty strony (rodzic, szablon, kolejność)',
};

const toggle = (list: string[], value: string) =>
    list.includes(value) ? list.filter((v) => v !== value) : [...list, value];

export default function ContentTypes({
    types,
    supportOptions,
    taxonomyOptions,
}: Props) {
    // null = formularz ukryty, 'new' = nowy typ, inaczej klucz edytowanego typu
    const [mode, setMode] = useState<string | null>(null);
    const [form, setForm] = useState<Form>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const editing = types.find((x) => x.key === mode) ?? null;

    const open = (key: string | null) => {
        setErrors({});
        const type = types.find((x) => x.key === key);
        setForm(
            type
                ? {
                      key: type.key,
                      label: type.label,
                      label_plural: type.label_plural,
                      icon: type.icon ?? 'boxes',
                      hierarchical: type.hierarchical,
                      supports: type.supports,
                      url_prefix: type.url_prefix ?? '',
                      capability_type: type.capability_type,
                      taxonomies: type.taxonomies,
                  }
                : emptyForm,
        );
        setMode(key ?? 'new');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...form,
            url_prefix: form.url_prefix || null,
            ...(editing ? { key: undefined } : {}),
        } as unknown as Record<string, never>;
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs: Record<string, string>) => setErrors(errs),
            onSuccess: () => setMode(null),
        };

        if (editing)
            router.put(cmsUrl.contentType(editing.key), payload, options);
        else router.post(cmsUrl.contentTypes, payload, options);
    };

    const remove = () => {
        if (
            editing &&
            confirm(t('Usunąć typ treści „{name}”?', { name: editing.label }))
        ) {
            router.delete(cmsUrl.contentType(editing.key), {
                onSuccess: () => setMode(null),
            });
        }
    };

    const field = (
        id: keyof Form,
        label: string,
        opts: {
            placeholder?: string;
            hint?: string;
            mono?: boolean;
            disabled?: boolean;
        } = {},
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`ct_${id}`}>{label}</Label>
            <Input
                id={`ct_${id}`}
                className={cn(opts.mono && 'font-mono')}
                value={String(form[id])}
                placeholder={opts.placeholder}
                disabled={opts.disabled}
                onChange={(e) => setForm({ ...form, [id]: e.target.value })}
            />
            {opts.hint && (
                <p className="text-xs text-muted-foreground">{opts.hint}</p>
            )}
            {errors[id] && (
                <p className="text-xs text-destructive">{errors[id]}</p>
            )}
        </div>
    );

    const locked = Boolean(editing?.is_system);

    return (
        <>
            <Head title={t('Typy treści')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-[26px] leading-tight font-bold tracking-tight">
                            {t('Typy treści')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Własne typy (np. Realizacje, Oferty, Wydarzenia) pojawiają się w menu i mają własne adresy, pola i taksonomie.',
                            )}
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => open(null)}>
                        <Plus /> {t('Nowy typ treści')}
                    </Button>
                </div>

                {mode && (
                    <Card className="gap-4 rounded-[14px] border-[#E7E7EA] py-5 shadow-none">
                        <CardHeader className="px-5">
                            <CardTitle className="text-base">
                                {editing
                                    ? t('Edytuj: {name}', {
                                          name: editing.label,
                                      })
                                    : t('Nowy typ treści')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-5">
                            <form
                                onSubmit={submit}
                                className="grid gap-5 sm:grid-cols-2"
                            >
                                {field(
                                    'label',
                                    t('Nazwa (liczba pojedyncza)'),
                                    {
                                        placeholder: t('Realizacja'),
                                    },
                                )}
                                {field(
                                    'label_plural',
                                    t('Nazwa (liczba mnoga)'),
                                    {
                                        placeholder: t('Realizacje'),
                                    },
                                )}
                                {field('key', t('Klucz'), {
                                    placeholder: 'realizacja',
                                    mono: true,
                                    disabled: Boolean(editing),
                                    hint: t(
                                        'Małe litery, cyfry i _. Nie można zmienić po utworzeniu.',
                                    ),
                                })}
                                {!locked &&
                                    field(
                                        'url_prefix',
                                        t('Przedrostek adresu'),
                                        {
                                            placeholder: t('domyślnie: klucz'),
                                            mono: true,
                                            hint: t(
                                                'Adresy: /przedrostek/slug',
                                            ),
                                        },
                                    )}

                                <div className="grid gap-1.5 sm:col-span-2">
                                    <Label>{t('Ikona w menu')}</Label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {Object.entries(typeIcons).map(
                                            ([name, Icon]) => (
                                                <button
                                                    key={name}
                                                    type="button"
                                                    title={name}
                                                    aria-label={name}
                                                    aria-pressed={
                                                        form.icon === name
                                                    }
                                                    onClick={() =>
                                                        setForm({
                                                            ...form,
                                                            icon: name,
                                                        })
                                                    }
                                                    className={cn(
                                                        'flex size-9 items-center justify-center rounded-[10px] border',
                                                        form.icon === name
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'text-muted-foreground hover:bg-muted',
                                                    )}
                                                >
                                                    <Icon className="size-4" />
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>{t('Funkcje')}</Label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {supportOptions.map((s) => (
                                            <label
                                                key={s}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.supports.includes(
                                                        s,
                                                    )}
                                                    onChange={() =>
                                                        setForm({
                                                            ...form,
                                                            supports: toggle(
                                                                form.supports,
                                                                s,
                                                            ),
                                                        })
                                                    }
                                                />
                                                {t(supportLabels[s] ?? s)}
                                            </label>
                                        ))}
                                    </div>
                                    {errors.supports && (
                                        <p className="text-xs text-destructive">
                                            {errors.supports}
                                        </p>
                                    )}
                                </div>

                                {!locked && (
                                    <div className="grid gap-2">
                                        <Label>{t('Struktura')}</Label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={form.hierarchical}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        hierarchical:
                                                            e.target.checked,
                                                    })
                                                }
                                            />
                                            {t(
                                                'Hierarchiczny (rodzic i dzieci, jak strony)',
                                            )}
                                        </label>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ct_cap">
                                                {t('Uprawnienia jak dla')}
                                            </Label>
                                            <select
                                                id="ct_cap"
                                                value={form.capability_type}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        capability_type: e
                                                            .target.value as
                                                            | 'post'
                                                            | 'page',
                                                    })
                                                }
                                                className="h-10 rounded-[10px] border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="post">
                                                    {t('Wpisów')}
                                                </option>
                                                <option value="page">
                                                    {t('Stron')}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                )}

                                {taxonomyOptions.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label>{t('Taksonomie')}</Label>
                                        {taxonomyOptions.map((x) => (
                                            <label
                                                key={x.key}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.taxonomies.includes(
                                                        x.key,
                                                    )}
                                                    onChange={() =>
                                                        setForm({
                                                            ...form,
                                                            taxonomies: toggle(
                                                                form.taxonomies,
                                                                x.key,
                                                            ),
                                                        })
                                                    }
                                                />
                                                {x.label}
                                            </label>
                                        ))}
                                    </div>
                                )}

                                <div className="flex gap-2 sm:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Loader2 className="animate-spin" />
                                        )}
                                        {t('Zapisz')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setMode(null)}
                                    >
                                        {t('Anuluj')}
                                    </Button>
                                    {editing && !editing.is_system && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="ml-auto text-destructive"
                                            onClick={remove}
                                        >
                                            <Trash2 /> {t('Usuń typ treści')}
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-0 rounded-[14px] border-[#E7E7EA] py-0 shadow-none">
                    {types.map((type) => {
                        const Icon = typeIcons[type.icon ?? ''] ?? Boxes;

                        return (
                            <div
                                key={type.key}
                                className="flex items-center gap-4 border-b px-5 py-4 last:border-b-0"
                            >
                                <div className="flex size-10 items-center justify-center rounded-[10px] bg-muted">
                                    <Icon className="size-5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {type.label_plural}{' '}
                                        {type.is_system && (
                                            <span className="ml-1 rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                                                {t('systemowy')}
                                            </span>
                                        )}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        <span className="font-mono">
                                            {type.key}
                                        </span>
                                        {' · '}
                                        {type.url_prefix
                                            ? `/${type.url_prefix}/…`
                                            : '/…'}
                                        {' · '}
                                        {t('{n} pozycji', {
                                            n: type.contents_count,
                                        })}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edytuj')}
                                    onClick={() => open(type.key)}
                                >
                                    <Pencil />
                                </Button>
                            </div>
                        );
                    })}
                </Card>
            </div>
        </>
    );
}
