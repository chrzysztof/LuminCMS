import { Head, router, usePage } from '@inertiajs/react';
import { Download, FileUp, Loader2, Play, ShieldCheck } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Counts = { created: number; updated: number; skipped: number };
type Report = {
    dry_run: boolean;
    applied: boolean;
    sections: Record<string, Counts>;
    errors: { where: string; message: string }[];
    notes: string[];
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const sectionLabels: Record<string, string> = {
    content_types: 'Typy treści',
    taxonomies: 'Taksonomie',
    terms: 'Terminy',
    field_groups: 'Grupy pól',
    contents: 'Treści',
};

export default function ImportPage({
    types,
    report,
}: {
    types: { key: string; label_plural: string }[];
    report: Report | null;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const [json, setJson] = useState('');
    const [existing, setExisting] = useState<'skip' | 'update'>('skip');
    const [busy, setBusy] = useState<'dry' | 'run' | null>(null);
    const [sections, setSections] = useState<string[]>([
        'content_types',
        'taxonomies',
        'field_groups',
    ]);
    const [exportTypes, setExportTypes] = useState<string[]>([]);

    const toggle = (
        list: string[],
        value: string,
        set: (v: string[]) => void,
    ) =>
        set(
            list.includes(value)
                ? list.filter((x) => x !== value)
                : [...list, value],
        );

    const run = (dry: boolean) => {
        setBusy(dry ? 'dry' : 'run');
        router.post(
            cmsUrl.importRun,
            { json, existing, dry_run: dry },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setBusy(null),
            },
        );
    };

    const exportHref =
        cmsUrl.exportJson +
        '?' +
        [
            ...sections.map((s) => `sections[]=${s}`),
            ...(sections.includes('contents')
                ? exportTypes.map((k) => `types[]=${encodeURIComponent(k)}`)
                : []),
        ].join('&');

    const exportOptions: [string, string][] = [
        ['content_types', t('Typy treści')],
        ['taxonomies', t('Taksonomie i terminy')],
        ['field_groups', t('Grupy pól')],
        ['contents', t('Treści')],
    ];

    return (
        <>
            <Head title={t('Import i eksport')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Import i eksport')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Przenoś typy treści, taksonomie z terminami, grupy pól i treści między instalacjami w jednym pliku JSON. Import najpierw sprawdź w trybie próbnym: nic nie zostanie zapisane, a raport pokaże dokładnie, co by się stało.',
                        )}
                    </p>
                </div>

                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Import')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    ref={fileInput}
                                    type="file"
                                    accept=".json,application/json"
                                    className="hidden"
                                    onChange={async (e) => {
                                        const f = e.target.files?.[0];
                                        if (f) setJson(await f.text());
                                        e.target.value = '';
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => fileInput.current?.click()}
                                >
                                    <FileUp />
                                    {t('Wybierz plik JSON')}
                                </Button>
                                <span className="text-xs text-muted-foreground">
                                    {t('albo wklej zawartość poniżej')}
                                </span>
                            </div>

                            <textarea
                                value={json}
                                onChange={(e) => setJson(e.target.value)}
                                aria-label={t('Zawartość pliku JSON')}
                                spellCheck={false}
                                rows={12}
                                placeholder='{"lumin": 1, "content_types": [], "taxonomies": [], "field_groups": [], "contents": []}'
                                className="w-full rounded-md border border-input bg-transparent p-3 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                            />
                            {errors.json && (
                                <p className="text-sm text-destructive">
                                    {errors.json}
                                </p>
                            )}

                            <div className="grid max-w-md gap-1.5">
                                <Label htmlFor="existing">
                                    {t(
                                        'Gdy element już istnieje (ten sam klucz lub slug)',
                                    )}
                                </Label>
                                <select
                                    id="existing"
                                    className={nativeClass}
                                    value={existing}
                                    onChange={(e) =>
                                        setExisting(
                                            e.target.value as 'skip' | 'update',
                                        )
                                    }
                                >
                                    <option value="skip">
                                        {t('Pomiń, nie zmieniaj')}
                                    </option>
                                    <option value="update">
                                        {t('Nadpisz danymi z pliku')}
                                    </option>
                                </select>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!json.trim() || busy !== null}
                                    onClick={() => run(true)}
                                >
                                    {busy === 'dry' ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <ShieldCheck />
                                    )}
                                    {t('Sprawdź (tryb próbny)')}
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!json.trim() || busy !== null}
                                    onClick={() => run(false)}
                                >
                                    {busy === 'run' ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Play />
                                    )}
                                    {t('Importuj')}
                                </Button>
                            </div>

                            {report && <ReportView report={report} />}
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Eksport')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <fieldset className="space-y-2">
                                <legend className="sr-only">
                                    {t('Co wyeksportować')}
                                </legend>
                                {exportOptions.map(([key, label]) => (
                                    <label
                                        key={key}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={sections.includes(key)}
                                            onChange={() =>
                                                toggle(
                                                    sections,
                                                    key,
                                                    setSections,
                                                )
                                            }
                                        />
                                        {label}
                                    </label>
                                ))}
                            </fieldset>

                            {sections.includes('contents') && (
                                <fieldset className="space-y-2 rounded-md border p-3">
                                    <legend className="px-1 text-xs text-muted-foreground">
                                        {t(
                                            'Typy treści do eksportu (puste = wszystkie)',
                                        )}
                                    </legend>
                                    {types.map((ty) => (
                                        <label
                                            key={ty.key}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={exportTypes.includes(
                                                    ty.key,
                                                )}
                                                onChange={() =>
                                                    toggle(
                                                        exportTypes,
                                                        ty.key,
                                                        setExportTypes,
                                                    )
                                                }
                                            />
                                            {ty.label_plural}
                                        </label>
                                    ))}
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Obrazy i pliki z biblioteki mediów nie są przenoszone; pola z ich identyfikatorami trzeba uzupełnić po imporcie.',
                                        )}
                                    </p>
                                </fieldset>
                            )}

                            <Button
                                asChild
                                className={cn(
                                    sections.length === 0 &&
                                        'pointer-events-none opacity-50',
                                )}
                            >
                                <a href={exportHref} download>
                                    <Download />
                                    {t('Pobierz plik JSON')}
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function ReportView({ report }: { report: Report }) {
    const failed = report.errors.length > 0;

    return (
        <div
            className={cn(
                'space-y-3 rounded-md border p-4 text-sm',
                failed ? 'border-destructive/50' : 'border-emerald-500/50',
            )}
            role="status"
        >
            <p className="font-medium">
                {failed
                    ? t(
                          'Import nie został wykonany: popraw błędy i spróbuj ponownie. Nic nie zapisano.',
                      )
                    : report.dry_run
                      ? t(
                            'Próba udana. Nic nie zapisano; tak wyglądałby import:',
                        )
                      : t('Import zakończony.')}
            </p>

            <table className="w-full text-left text-xs">
                <thead className="text-muted-foreground">
                    <tr>
                        <th className="py-1 font-medium">{t('Sekcja')}</th>
                        <th className="py-1 font-medium">{t('Nowe')}</th>
                        <th className="py-1 font-medium">
                            {t('Zaktualizowane')}
                        </th>
                        <th className="py-1 font-medium">{t('Pominięte')}</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.entries(report.sections).map(([key, c]) => (
                        <tr key={key} className="border-t">
                            <td className="py-1">
                                {t(sectionLabels[key] ?? key)}
                            </td>
                            <td className="py-1">{c.created}</td>
                            <td className="py-1">{c.updated}</td>
                            <td className="py-1">{c.skipped}</td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {failed && (
                <ul className="space-y-1 text-destructive">
                    {report.errors.map((e, i) => (
                        <li key={i}>
                            <span className="font-medium">{e.where}:</span>{' '}
                            {e.message}
                        </li>
                    ))}
                </ul>
            )}

            {report.notes.length > 0 && (
                <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                    {report.notes.map((n, i) => (
                        <li key={i}>{n}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
