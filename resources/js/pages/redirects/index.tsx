import { Head, Link, router } from '@inertiajs/react';
import { Loader2, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { cn } from '@/lib/utils';

type Redirect = {
    id: number;
    source: string;
    target: string | null;
    status_code: number;
    active: boolean;
    auto: boolean;
    note: string | null;
    hits: number;
    last_hit_at: string | null;
};

type Log = {
    id: number;
    path: string;
    hits: number;
    last_referrer: string | null;
    last_seen_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Props = {
    redirects: Paginated<Redirect>;
    q: string;
    logs: Log[];
    notFoundPageId: number | null;
    pages: { id: number; title: string }[];
    codes: number[];
};

type FormState = {
    source: string;
    target: string;
    status_code: number;
    note: string;
    active: boolean;
};

const empty: FormState = {
    source: '',
    target: '',
    status_code: 301,
    note: '',
    active: true,
};

const codeLabels: Record<number, string> = {
    301: '301 – stałe',
    302: '302 – tymczasowe',
    307: '307 – tymczasowe (zachowuje metodę)',
    308: '308 – stałe (zachowuje metodę)',
    410: '410 – usunięte na stałe',
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const pageLabel = (html: string) =>
    html.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');

export default function RedirectsIndex({
    redirects,
    q,
    logs,
    notFoundPageId,
    pages,
    codes,
}: Props) {
    const [tab, setTab] = useState<'redirects' | 'logs'>('redirects');
    const [form, setForm] = useState<FormState>(empty);
    const [editing, setEditing] = useState<number | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState(q);
    const [notFoundId, setNotFoundId] = useState<number | null>(notFoundPageId);

    const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
        setForm((f) => ({ ...f, [key]: value }));

    const reset = () => {
        setForm(empty);
        setEditing(null);
        setErrors({});
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs: Record<string, string>) => setErrors(errs),
            onSuccess: reset,
        };
        const payload = form as unknown as Record<string, never>;

        if (editing)
            router.put(cmsUrl.redirectUpdate(editing), payload, options);
        else router.post(cmsUrl.redirectIndex, payload, options);
    };

    const edit = (r: Redirect) => {
        setTab('redirects');
        setEditing(r.id);
        setErrors({});
        setForm({
            source: r.source,
            target: r.target ?? '',
            status_code: r.status_code,
            note: r.note ?? '',
            active: r.active,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const fromLog = (l: Log) => {
        setTab('redirects');
        setEditing(null);
        setErrors({});
        setForm({ ...empty, source: l.path });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const doSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(cmsUrl.redirectIndex, search ? { q: search } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    const saveNotFoundPage = () =>
        router.put(
            cmsUrl.redirectPages,
            { not_found_content_id: notFoundId } as unknown as Record<
                string,
                never
            >,
            { preserveScroll: true },
        );

    const tabClass = (active: boolean) =>
        cn(
            'border-b-2 px-3 py-2 text-sm font-medium',
            active
                ? 'border-brand text-brand'
                : 'border-transparent text-muted-foreground hover:text-foreground',
        );

    return (
        <>
            <Head title="Przekierowania" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Przekierowania i błędy 404
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Zmiana adresu opublikowanej strony tworzy przekierowanie
                        301 automatycznie.
                    </p>
                </div>

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {editing
                                ? 'Edytuj przekierowanie'
                                : 'Nowe przekierowanie'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 lg:grid-cols-[1fr_1fr_14rem]"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="r-source">Z adresu</Label>
                                <Input
                                    id="r-source"
                                    value={form.source}
                                    placeholder="/stara-strona"
                                    onChange={(e) =>
                                        set('source', e.target.value)
                                    }
                                />
                                {errors.source && (
                                    <p className="text-xs text-destructive">
                                        {errors.source}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="r-target">Na adres</Label>
                                <Input
                                    id="r-target"
                                    value={
                                        form.status_code === 410
                                            ? ''
                                            : form.target
                                    }
                                    disabled={form.status_code === 410}
                                    placeholder={
                                        form.status_code === 410
                                            ? 'Nie dotyczy (410)'
                                            : '/nowa-strona lub https://…'
                                    }
                                    onChange={(e) =>
                                        set('target', e.target.value)
                                    }
                                />
                                {errors.target && (
                                    <p className="text-xs text-destructive">
                                        {errors.target}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="r-code">Rodzaj</Label>
                                <select
                                    id="r-code"
                                    className={nativeClass}
                                    value={form.status_code}
                                    onChange={(e) =>
                                        set(
                                            'status_code',
                                            Number(e.target.value),
                                        )
                                    }
                                >
                                    {codes.map((c) => (
                                        <option key={c} value={c}>
                                            {codeLabels[c] ?? c}
                                        </option>
                                    ))}
                                </select>
                                {errors.status_code && (
                                    <p className="text-xs text-destructive">
                                        {errors.status_code}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5 lg:col-span-2">
                                <Label htmlFor="r-note">
                                    Notatka (opcjonalnie)
                                </Label>
                                <Input
                                    id="r-note"
                                    value={form.note}
                                    onChange={(e) =>
                                        set('note', e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Plus />
                                    )}{' '}
                                    {editing ? 'Zapisz' : 'Dodaj'}
                                </Button>
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={reset}
                                    >
                                        Anuluj
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex gap-1 border-b">
                    <button
                        type="button"
                        className={tabClass(tab === 'redirects')}
                        onClick={() => setTab('redirects')}
                    >
                        Przekierowania ({redirects.total})
                    </button>
                    <button
                        type="button"
                        className={tabClass(tab === 'logs')}
                        onClick={() => setTab('logs')}
                    >
                        Błędy 404 ({logs.length})
                    </button>
                </div>

                {tab === 'redirects' && (
                    <>
                        <form
                            onSubmit={doSearch}
                            className="flex max-w-md gap-2"
                        >
                            <Input
                                value={search}
                                placeholder="Szukaj adresu lub notatki…"
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button type="submit" variant="outline">
                                Szukaj
                            </Button>
                        </form>
                        <Card className="py-0">
                            <CardContent className="divide-y p-0">
                                {redirects.data.length === 0 && (
                                    <p className="p-6 text-sm text-muted-foreground">
                                        Brak przekierowań.
                                    </p>
                                )}
                                {redirects.data.map((r) => (
                                    <div
                                        key={r.id}
                                        className={cn(
                                            'flex flex-wrap items-center gap-3 p-4',
                                            !r.active && 'opacity-50',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            role="switch"
                                            aria-checked={r.active}
                                            aria-label={`Aktywne: ${r.source}`}
                                            onClick={() =>
                                                router.post(
                                                    cmsUrl.redirectToggle(r.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className={cn(
                                                'relative h-5 w-9 shrink-0 rounded-full transition-colors',
                                                r.active
                                                    ? 'bg-brand'
                                                    : 'bg-input',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'absolute top-0.5 size-4 rounded-full bg-white shadow transition-all',
                                                    r.active
                                                        ? 'left-[1.1rem]'
                                                        : 'left-0.5',
                                                )}
                                            />
                                        </button>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-mono text-sm">
                                                {r.source}{' '}
                                                <span className="text-muted-foreground">
                                                    →
                                                </span>{' '}
                                                {r.target ?? 'usunięta (410)'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.status_code}
                                                {r.auto && ' · automatyczne'}
                                                {r.note &&
                                                    ` · ${r.note}`} ·{' '}
                                                {r.hits} wejść
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Edytuj"
                                            onClick={() => edit(r)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Usuń"
                                            className="text-destructive"
                                            onClick={() =>
                                                confirm(
                                                    `Usunąć przekierowanie z ${r.source}?`,
                                                ) &&
                                                router.delete(
                                                    cmsUrl.redirectUpdate(r.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        {redirects.links.length > 3 && (
                            <nav
                                className="flex flex-wrap gap-1"
                                aria-label="Strony"
                            >
                                {redirects.links.map((l, i) =>
                                    l.url ? (
                                        <Link
                                            key={i}
                                            href={l.url}
                                            preserveScroll
                                            className={cn(
                                                'rounded border px-3 py-1 text-sm',
                                                l.active &&
                                                    'bg-brand text-white',
                                            )}
                                        >
                                            {pageLabel(l.label)}
                                        </Link>
                                    ) : (
                                        <span
                                            key={i}
                                            className="rounded border px-3 py-1 text-sm opacity-40"
                                        >
                                            {pageLabel(l.label)}
                                        </span>
                                    ),
                                )}
                            </nav>
                        )}
                    </>
                )}

                {tab === 'logs' && (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                Adresy, których nie udało się znaleźć, od
                                najczęstszych.
                            </p>
                            {logs.length > 0 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        confirm('Wyczyścić całą listę?') &&
                                        router.delete(cmsUrl.redirectLogs, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Wyczyść listę
                                </Button>
                            )}
                        </div>
                        <Card className="py-0">
                            <CardContent className="divide-y p-0">
                                {logs.length === 0 && (
                                    <p className="p-6 text-sm text-muted-foreground">
                                        Nie zarejestrowano jeszcze żadnych
                                        błędów 404.
                                    </p>
                                )}
                                {logs.map((l) => (
                                    <div
                                        key={l.id}
                                        className="flex flex-wrap items-center gap-3 p-4"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-mono text-sm">
                                                {l.path}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {l.hits} wejść
                                                {l.last_referrer
                                                    ? ` · z: ${l.last_referrer}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => fromLog(l)}
                                        >
                                            Przekieruj
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Ukryj"
                                            onClick={() =>
                                                router.delete(
                                                    cmsUrl.redirectLog(l.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </>
                )}

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Strona błędu 404
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Domyślnie pokazujemy wbudowaną stronę. Możesz
                            wskazać własną stronę zbudowaną w edytorze
                            wizualnym; odpowiedź nadal ma kod 404 i nie jest
                            indeksowana.
                        </p>
                        <div className="flex max-w-xl gap-2">
                            <select
                                aria-label="Strona 404"
                                className={nativeClass}
                                value={notFoundId ?? ''}
                                onChange={(e) =>
                                    setNotFoundId(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                            >
                                <option value="">Domyślna strona Lumin</option>
                                {pages.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.title}
                                    </option>
                                ))}
                            </select>
                            <Button type="button" onClick={saveNotFoundPage}>
                                Zapisz
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
