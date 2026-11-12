import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Pencil, Plus, Settings2, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Taxonomy = {
    id: number;
    key: string;
    label: string;
    label_plural: string;
    hierarchical: boolean;
    is_system: boolean;
    terms_count: number;
    content_types: string[];
};

type TermRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    depth: number;
    count: number;
};

type Props = {
    taxonomies: Taxonomy[];
    current: string | null;
    terms: TermRow[];
    typeOptions: { key: string; label: string }[];
    canManage: boolean;
};

type TermForm = {
    name: string;
    slug: string;
    description: string;
    parent_id: number | null;
};

type TaxForm = {
    key: string;
    label: string;
    label_plural: string;
    hierarchical: boolean;
    content_types: string[];
};

const emptyTerm: TermForm = {
    name: '',
    slug: '',
    description: '',
    parent_id: null,
};
const emptyTax: TaxForm = {
    key: '',
    label: '',
    label_plural: '',
    hierarchical: false,
    content_types: [],
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const asPayload = (v: unknown) => v as Record<string, never>;

export default function TaxonomiesIndex({
    taxonomies,
    current,
    terms,
    typeOptions,
    canManage,
}: Props) {
    const tax = taxonomies.find((x) => x.key === current) ?? null;
    const [form, setForm] = useState<TermForm>(emptyTerm);
    const [editing, setEditing] = useState<number | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    // 'new' = nowa taksonomia, 'edit' = ustawienia bieżącej, null = ukryte
    const [taxMode, setTaxMode] = useState<'new' | 'edit' | null>(null);
    const [taxForm, setTaxForm] = useState<TaxForm>(emptyTax);

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onError: (errs: Record<string, string>) => setErrors(errs),
    };

    const resetTerm = () => {
        setForm(emptyTerm);
        setEditing(null);
        setErrors({});
    };

    const submitTerm = (e: React.FormEvent) => {
        e.preventDefault();
        if (!tax) return;
        const done = { ...options, onSuccess: resetTerm };

        if (editing)
            router.put(cmsUrl.term(tax.key, editing), asPayload(form), done);
        else router.post(cmsUrl.termStore(tax.key), asPayload(form), done);
    };

    const editTerm = (term: TermRow) => {
        setEditing(term.id);
        setErrors({});
        setForm({
            name: term.name,
            slug: term.slug,
            description: term.description ?? '',
            parent_id: term.parent_id,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const removeTerm = (term: TermRow) => {
        if (!tax) return;
        if (confirm(t('Usunąć termin „{name}”?', { name: term.name })))
            router.delete(cmsUrl.term(tax.key, term.id), {
                preserveScroll: true,
            });
    };

    const openTax = (mode: 'new' | 'edit') => {
        setErrors({});
        setTaxMode(mode);
        setTaxForm(
            mode === 'edit' && tax
                ? {
                      key: tax.key,
                      label: tax.label,
                      label_plural: tax.label_plural,
                      hierarchical: tax.hierarchical,
                      content_types: tax.content_types,
                  }
                : emptyTax,
        );
    };

    const submitTax = (e: React.FormEvent) => {
        e.preventDefault();
        const done = { ...options, onSuccess: () => setTaxMode(null) };
        const { key, ...rest } = taxForm;

        if (taxMode === 'edit' && tax)
            router.put(cmsUrl.taxonomy(tax.key), asPayload(rest), done);
        else router.post(cmsUrl.taxonomies, asPayload(taxForm), done);

        void key;
    };

    const removeTax = () => {
        if (
            tax &&
            confirm(
                t('Usunąć taksonomię „{name}” razem ze wszystkimi terminami?', {
                    name: tax.label_plural,
                }),
            )
        )
            router.delete(cmsUrl.taxonomy(tax.key));
    };

    const toggleType = (key: string) =>
        setTaxForm((f) => ({
            ...f,
            content_types: f.content_types.includes(key)
                ? f.content_types.filter((k) => k !== key)
                : [...f.content_types, key],
        }));

    const parentOptions = terms.filter((x) => x.id !== editing);

    return (
        <>
            <Head title={t('Kategorie i tagi')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Kategorie i tagi')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Taksonomie grupują treści: kategorie tworzą drzewo, tagi są płaską listą.',
                            )}
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            variant="outline"
                            onClick={() => openTax('new')}
                        >
                            <Plus /> {t('Nowa taksonomia')}
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap gap-1">
                    {taxonomies.map((x) => (
                        <Link
                            key={x.key}
                            href={`${cmsUrl.taxonomies}?taxonomy=${x.key}`}
                            onClick={resetTerm}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-sm transition-colors',
                                x.key === current
                                    ? 'bg-brand-soft font-medium text-brand-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {x.label_plural}{' '}
                            <span className="tabular-nums opacity-70">
                                {x.terms_count}
                            </span>
                        </Link>
                    ))}
                </div>

                {taxMode && (
                    <Card className="gap-4">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                {taxMode === 'new'
                                    ? t('Nowa taksonomia')
                                    : t('Ustawienia taksonomii')}
                            </CardTitle>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Zamknij')}
                                onClick={() => setTaxMode(null)}
                            >
                                <X />
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitTax}
                                className="grid gap-4 sm:grid-cols-2"
                            >
                                {taxMode === 'new' && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="tax_key">
                                            {t('Klucz')}
                                        </Label>
                                        <Input
                                            id="tax_key"
                                            className="font-mono"
                                            value={taxForm.key}
                                            placeholder={t('np. temat')}
                                            onChange={(e) =>
                                                setTaxForm({
                                                    ...taxForm,
                                                    key: e.target.value,
                                                })
                                            }
                                        />
                                        {errors.key && (
                                            <p className="text-xs text-destructive">
                                                {errors.key}
                                            </p>
                                        )}
                                    </div>
                                )}
                                <div className="grid gap-1.5">
                                    <Label htmlFor="tax_label">
                                        {t('Nazwa (liczba pojedyncza)')}
                                    </Label>
                                    <Input
                                        id="tax_label"
                                        value={taxForm.label}
                                        onChange={(e) =>
                                            setTaxForm({
                                                ...taxForm,
                                                label: e.target.value,
                                            })
                                        }
                                    />
                                    {errors.label && (
                                        <p className="text-xs text-destructive">
                                            {errors.label}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="tax_plural">
                                        {t('Nazwa (liczba mnoga)')}
                                    </Label>
                                    <Input
                                        id="tax_plural"
                                        value={taxForm.label_plural}
                                        onChange={(e) =>
                                            setTaxForm({
                                                ...taxForm,
                                                label_plural: e.target.value,
                                            })
                                        }
                                    />
                                    {errors.label_plural && (
                                        <p className="text-xs text-destructive">
                                            {errors.label_plural}
                                        </p>
                                    )}
                                </div>
                                {!(taxMode === 'edit' && tax?.is_system) && (
                                    <label className="flex items-center gap-2 self-end text-sm">
                                        <input
                                            type="checkbox"
                                            checked={taxForm.hierarchical}
                                            onChange={(e) =>
                                                setTaxForm({
                                                    ...taxForm,
                                                    hierarchical:
                                                        e.target.checked,
                                                })
                                            }
                                        />
                                        {t('Hierarchiczna (jak kategorie)')}
                                    </label>
                                )}
                                <div className="grid gap-1.5 sm:col-span-2">
                                    <Label>{t('Typy treści')}</Label>
                                    <div className="flex flex-wrap gap-4">
                                        {typeOptions.map((c) => (
                                            <label
                                                key={c.key}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={taxForm.content_types.includes(
                                                        c.key,
                                                    )}
                                                    onChange={() =>
                                                        toggleType(c.key)
                                                    }
                                                />
                                                {c.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div className="flex gap-2 sm:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Loader2 className="animate-spin" />
                                        )}
                                        {t('Zapisz')}
                                    </Button>
                                    {taxMode === 'edit' &&
                                        tax &&
                                        !tax.is_system && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="text-destructive"
                                                onClick={removeTax}
                                            >
                                                <Trash2 />{' '}
                                                {t('Usuń taksonomię')}
                                            </Button>
                                        )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {tax ? (
                    <div className="grid gap-5 lg:grid-cols-[22rem_1fr]">
                        <Card className="h-fit gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {editing
                                        ? t('Edytuj termin')
                                        : t('Nowy termin: {name}', {
                                              name: tax.label,
                                          })}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitTerm}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="term_name">
                                            {t('Nazwa')}
                                        </Label>
                                        <Input
                                            id="term_name"
                                            value={form.name}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                        {errors.name && (
                                            <p className="text-xs text-destructive">
                                                {errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="term_slug">
                                            {t('Adres (slug)')}
                                        </Label>
                                        <Input
                                            id="term_slug"
                                            className="font-mono"
                                            value={form.slug}
                                            placeholder={t(
                                                'generowany z tytułu',
                                            )}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    slug: e.target.value,
                                                })
                                            }
                                        />
                                        {errors.slug && (
                                            <p className="text-xs text-destructive">
                                                {errors.slug}
                                            </p>
                                        )}
                                    </div>
                                    {tax.hierarchical && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="term_parent">
                                                {t('Termin nadrzędny')}
                                            </Label>
                                            <select
                                                id="term_parent"
                                                className={nativeClass}
                                                value={form.parent_id ?? ''}
                                                onChange={(e) =>
                                                    setForm({
                                                        ...form,
                                                        parent_id: e.target
                                                            .value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    {t('— brak —')}
                                                </option>
                                                {parentOptions.map((x) => (
                                                    <option
                                                        key={x.id}
                                                        value={x.id}
                                                    >
                                                        {'– '.repeat(x.depth)}
                                                        {x.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.parent_id && (
                                                <p className="text-xs text-destructive">
                                                    {errors.parent_id}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="term_desc">
                                            {t('Opis')}
                                        </Label>
                                        <textarea
                                            id="term_desc"
                                            rows={3}
                                            className={cn(
                                                nativeClass,
                                                'h-auto',
                                            )}
                                            value={form.description}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    description: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && (
                                                <Loader2 className="animate-spin" />
                                            )}
                                            {editing ? t('Zapisz') : t('Dodaj')}
                                        </Button>
                                        {editing && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={resetTerm}
                                            >
                                                {t('Anuluj')}
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card className="gap-0 overflow-hidden py-0">
                            <div className="flex items-center justify-between border-b bg-muted/40 px-4 py-2.5 text-xs font-medium text-muted-foreground uppercase">
                                <span>{tax.label_plural}</span>
                                {canManage && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openTax('edit')}
                                    >
                                        <Settings2 />{' '}
                                        {t('Ustawienia taksonomii')}
                                    </Button>
                                )}
                            </div>
                            {terms.length === 0 ? (
                                <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                                    {t('Brak terminów.')}
                                </p>
                            ) : (
                                terms.map((term) => (
                                    <div
                                        key={term.id}
                                        className="flex items-center gap-3 border-b px-4 py-2.5 text-sm last:border-b-0"
                                    >
                                        <div
                                            className="min-w-0 flex-1"
                                            style={{
                                                paddingLeft: term.depth * 20,
                                            }}
                                        >
                                            <span className="font-medium">
                                                {term.name}
                                            </span>{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {term.slug}
                                            </span>
                                        </div>
                                        <span className="text-muted-foreground tabular-nums">
                                            {t('{n} pozycji', {
                                                n: term.count,
                                            })}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Edytuj')}
                                            onClick={() => editTerm(term)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Usuń')}
                                            className="text-destructive"
                                            onClick={() => removeTerm(term)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                ))
                            )}
                        </Card>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('Brak taksonomii.')}
                    </p>
                )}
            </div>
        </>
    );
}
