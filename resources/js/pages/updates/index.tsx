import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    ExternalLink,
    Loader2,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Release = {
    version: string;
    name: string;
    notes: string;
    url: string;
    publishedAt: string | null;
    assetSize: number;
};

type Props = {
    current: string;
    enabled: boolean;
    release: Release | null;
    available: boolean;
    installable: boolean;
    blocker: string | null;
    checkedAt: number | null;
    error: string | null;
};

const mb = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

export default function Updates({
    current,
    enabled,
    release,
    available,
    installable,
    blocker,
    checkedAt,
    error,
}: Props) {
    const [busy, setBusy] = useState<'check' | 'install' | null>(null);

    const run = (kind: 'check' | 'install') => {
        if (
            kind === 'install' &&
            !window.confirm(
                t(
                    'Zaktualizować system? Strona będzie na chwilę w trybie konserwacji, a przed podmianą plików powstanie kopia zapasowa. Zalecamy wcześniej wykonać kopię bazy danych.',
                ),
            )
        ) {
            return;
        }
        router.post(
            kind === 'check' ? cmsUrl.updatesCheck : cmsUrl.updatesInstall,
            {},
            {
                onStart: () => setBusy(kind),
                onFinish: () => setBusy(null),
            },
        );
    };

    return (
        <>
            <Head title={t('Aktualizacje')} />
            <div className="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {t('Aktualizacje')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('Zainstalowana wersja')}:{' '}
                            <strong>{current}</strong>
                            {checkedAt && (
                                <>
                                    {' · '}
                                    {t('sprawdzono')}{' '}
                                    {new Date(
                                        checkedAt * 1000,
                                    ).toLocaleString()}
                                </>
                            )}
                        </p>
                    </div>
                    {enabled && (
                        <Button
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => run('check')}
                        >
                            {busy === 'check' ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            {t('Sprawdź teraz')}
                        </Button>
                    )}
                </div>

                {!enabled && (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            {t(
                                'Sprawdzanie aktualizacji jest wyłączone (CMS_UPDATE_CHECKS=false).',
                            )}
                        </CardContent>
                    </Card>
                )}

                {error && (
                    <p className="rounded-md border border-destructive/40 p-3 text-sm text-destructive">
                        {t('Nie udało się sprawdzić aktualizacji')}: {error}
                    </p>
                )}

                {enabled && !available && !error && (
                    <Card>
                        <CardContent className="flex items-center gap-2 pt-6 text-sm">
                            <CheckCircle2 className="size-4 text-emerald-600" />
                            {t('Masz aktualną wersję systemu.')}
                        </CardContent>
                    </Card>
                )}

                {available && release && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    {t('Dostępna wersja')} {release.version}
                                </span>
                                <a
                                    href={release.url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground hover:text-foreground"
                                >
                                    GitHub <ExternalLink className="size-3" />
                                </a>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {release.notes && (
                                <pre className="max-h-72 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap">
                                    {release.notes}
                                </pre>
                            )}
                            {installable && !blocker ? (
                                <div className="space-y-2">
                                    <Button
                                        disabled={busy !== null}
                                        onClick={() => run('install')}
                                    >
                                        {busy === 'install' ? (
                                            <Loader2 className="size-4 animate-spin" />
                                        ) : (
                                            <Download className="size-4" />
                                        )}
                                        {busy === 'install'
                                            ? t(
                                                  'Aktualizowanie… nie zamykaj strony',
                                              )
                                            : `${t('Zaktualizuj do')} ${release.version} (${mb(release.assetSize)})`}
                                    </Button>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Paczka jest pobierana z GitHuba i weryfikowana sumą SHA-256. Pliki .env, storage, baza danych i własne motywy zostają nietknięte; przy błędzie system wraca do poprzedniej wersji.',
                                        )}
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-1 rounded-md border p-3 text-sm">
                                    <p>
                                        {blocker ??
                                            t(
                                                'To wydanie nie ma paczki z sumą kontrolną.',
                                            )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'Aktualizacja ręczna: pobierz paczkę z GitHuba i podmień pliki (z wyjątkiem .env i storage) albo uruchom git pull, composer install, php artisan migrate.',
                                        )}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
