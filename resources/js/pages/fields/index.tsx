import { Head, Link, router } from '@inertiajs/react';
import { Copy, Download, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Group = {
    id: number;
    key: string;
    title: string;
    active: boolean;
    fields_count: number;
    location: string;
};

export default function FieldGroupIndex({
    groups,
    errors,
}: {
    groups: Group[];
    errors?: Record<string, string>;
}) {
    const [importing, setImporting] = useState(false);
    const [json, setJson] = useState('');

    const readFile = async (file: File | undefined) => {
        if (file) setJson(await file.text());
    };

    const doImport = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            cmsUrl.fieldImport,
            { json },
            {
                onSuccess: () => {
                    setJson('');
                    setImporting(false);
                },
            },
        );
    };

    return (
        <>
            <Head title={t('Pola własne')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Pola własne')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Grupy pól dodawane do edytora treści według reguł lokalizacji.',
                            )}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setImporting(!importing)}
                        >
                            <Upload /> {t('Import')}
                        </Button>
                        <Button asChild>
                            <Link href={cmsUrl.fieldCreate}>
                                <Plus /> {t('Nowa grupa')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {importing && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Import grup pól (JSON)')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={doImport} className="space-y-3">
                                <input
                                    type="file"
                                    accept="application/json,.json"
                                    onChange={(e) =>
                                        void readFile(e.target.files?.[0])
                                    }
                                />
                                <textarea
                                    aria-label={t('Zawartość JSON')}
                                    rows={8}
                                    value={json}
                                    onChange={(e) => setJson(e.target.value)}
                                    className="w-full rounded-md border border-input bg-transparent p-3 font-mono text-xs"
                                    placeholder='{"lumin":1,"field_groups":[...]}'
                                />
                                {errors?.json && (
                                    <p className="text-sm text-destructive">
                                        {errors.json}
                                    </p>
                                )}
                                <Button type="submit" disabled={!json.trim()}>
                                    {t('Importuj')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="py-0">
                    <CardContent className="divide-y p-0">
                        {groups.length === 0 && (
                            <p className="p-6 text-sm text-muted-foreground">
                                {t('Nie ma jeszcze żadnych grup pól.')}
                            </p>
                        )}
                        {groups.map((g) => (
                            <div
                                key={g.id}
                                className="flex flex-wrap items-center gap-3 p-4"
                            >
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked={g.active}
                                    aria-label={t('Aktywna: {name}', {
                                        name: g.title,
                                    })}
                                    onClick={() =>
                                        router.post(
                                            cmsUrl.fieldToggle(g.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${g.active ? 'bg-brand' : 'bg-input'}`}
                                >
                                    <span
                                        className={`absolute top-0.5 size-4 rounded-full bg-white shadow transition-all ${g.active ? 'left-[1.1rem]' : 'left-0.5'}`}
                                    />
                                </button>
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={cmsUrl.fieldEdit(g.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {g.title}
                                    </Link>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {t('{n} pól', { n: g.fields_count })} ·{' '}
                                        {g.location}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edytuj')}
                                >
                                    <Link href={cmsUrl.fieldEdit(g.id)}>
                                        <Pencil />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Duplikuj')}
                                    onClick={() =>
                                        router.post(cmsUrl.fieldDuplicate(g.id))
                                    }
                                >
                                    <Copy />
                                </Button>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Eksport')}
                                >
                                    <a href={cmsUrl.fieldExport(g.id)}>
                                        <Download />
                                    </a>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Usuń')}
                                    className="text-destructive"
                                    onClick={() =>
                                        confirm(
                                            t('Usunąć grupę „{name}”?', {
                                                name: g.title,
                                            }),
                                        ) &&
                                        router.delete(cmsUrl.fieldUpdate(g.id))
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
