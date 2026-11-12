import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    Download,
    Loader2,
    Palette,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type ThemeRow = {
    slug: string;
    name: string;
    version: string;
    author: string;
    description: string;
    parent: string | null;
    parent_name: string | null;
    error: string | null;
    active: boolean;
    is_default: boolean;
    has_screenshot: boolean;
    can_delete: boolean;
};

export default function ThemesIndex({
    themes,
    uploadsEnabled,
}: {
    themes: ThemeRow[];
    uploadsEnabled: boolean;
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = (file: File | undefined) => {
        if (!file) return;
        setUploading(true);
        router.post(
            cmsUrl.themes,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    if (input.current) input.current.value = '';
                },
            },
        );
    };

    const remove = (theme: ThemeRow) => {
        if (confirm(t('Usunąć motyw „{name}”?', { name: theme.name })))
            router.delete(cmsUrl.theme(theme.slug), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Motywy')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Motywy')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Motyw decyduje o wyglądzie witryny: szablonach, nagłówku i stopce, blokach edytora i ustawieniach „Dostosuj”. Motyw potomny dziedziczy po innym motywie i nadpisuje tylko to, co zmienia.',
                        )}
                    </p>
                </div>

                {errors.theme && (
                    <p
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {errors.theme}
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {themes.map((th) => (
                        <Card
                            key={th.slug}
                            className="gap-0 overflow-hidden py-0"
                        >
                            <div className="flex aspect-[16/9] items-center justify-center bg-muted">
                                {th.has_screenshot ? (
                                    <img
                                        src={cmsUrl.themeScreenshot(th.slug)}
                                        alt=""
                                        className="size-full object-cover"
                                        loading="lazy"
                                    />
                                ) : (
                                    <Palette className="size-10 text-muted-foreground/50" />
                                )}
                            </div>
                            <CardHeader className="gap-1 pt-4">
                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                    {th.name}
                                    {th.active && (
                                        <Badge>
                                            <Check /> {t('Aktywny')}
                                        </Badge>
                                    )}
                                    {th.parent && (
                                        <Badge variant="secondary">
                                            {t('Potomny')}: {th.parent_name}
                                        </Badge>
                                    )}
                                </CardTitle>
                                <p className="text-xs text-muted-foreground">
                                    {[th.version && `v${th.version}`, th.author]
                                        .filter(Boolean)
                                        .join(' · ') || th.slug}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-2 pb-4">
                                {th.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {th.description}
                                    </p>
                                )}
                                {th.error && (
                                    <p className="text-xs text-destructive">
                                        {th.error === 'cycle'
                                            ? t(
                                                  'Dziedziczenie motywów jest cykliczne.',
                                              )
                                            : t(
                                                  'Brakuje motywu nadrzędnego: {name}.',
                                                  {
                                                      name: th.parent ?? '',
                                                  },
                                              )}
                                    </p>
                                )}
                                <div className="flex flex-wrap gap-2">
                                    {!th.active && (
                                        <Button
                                            size="sm"
                                            disabled={th.error !== null}
                                            onClick={() =>
                                                router.post(
                                                    cmsUrl.themeActivate(
                                                        th.slug,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('Aktywuj')}
                                        </Button>
                                    )}
                                    <Button asChild size="sm" variant="outline">
                                        <a href={cmsUrl.themeExport(th.slug)}>
                                            <Download />
                                            {t('Eksportuj')}
                                        </a>
                                    </Button>
                                    {th.can_delete && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            aria-label={t('Usuń')}
                                            onClick={() => remove(th)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {uploadsEnabled && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Zainstaluj motyw z pliku ZIP')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Motyw to zaufany kod (szablony Blade są wykonywane na serwerze). Instaluj tylko motywy z pewnych źródeł. Archiwum musi zawierać theme.json i widoki motywu albo wskazywać zainstalowany motyw nadrzędny.',
                                )}
                            </p>
                            <input
                                ref={input}
                                type="file"
                                accept=".zip,application/zip"
                                className="hidden"
                                onChange={(e) => upload(e.target.files?.[0])}
                            />
                            <Button
                                type="button"
                                disabled={uploading}
                                onClick={() => input.current?.click()}
                            >
                                {uploading ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Upload />
                                )}
                                {t('Wybierz plik ZIP')}
                            </Button>
                            {errors.file && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.file}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
