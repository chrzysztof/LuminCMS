import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Report = {
    daily: { date: string; users: number; sessions: number; views: number }[];
    totals: { users: number; sessions: number; views: number };
    pages: { path: string; views: number }[];
};

const W = 640;
const H = 150;

/** Odwiedziny z Google Analytics 4 (ostatnie 30 dni). Ładowane osobno, więc wolne API nie spowalnia pulpitu. */
export function AnalyticsWidget() {
    const [report, setReport] = useState<Report | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        fetch(cmsUrl.analytics, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (res) => {
                const json = await res.json();

                if (!res.ok) throw new Error(json.error ?? 'error');

                setReport(json as Report);
            })
            .catch((e: Error) => {
                if (e.name !== 'AbortError') setError(e.message);
            });

        return () => controller.abort();
    }, []);

    const lang = document.documentElement.lang || undefined;
    const fmt = (n: number) => n.toLocaleString(lang);
    const max = Math.max(1, ...(report?.daily.map((d) => d.views) ?? [1]));
    const bar = W / Math.max(1, report?.daily.length ?? 1);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {t('Odwiedziny (30 dni)')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {error && (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Nie udało się pobrać statystyk z Google Analytics.',
                        )}{' '}
                        <span className="text-xs">{error}</span>
                    </p>
                )}
                {!error && !report && (
                    <div className="flex h-32 items-center justify-center text-muted-foreground">
                        <Loader2 className="animate-spin" />
                    </div>
                )}
                {report && (
                    <div className="grid gap-6 lg:grid-cols-[1fr_16rem]">
                        <div>
                            <div className="mb-4 flex flex-wrap gap-8">
                                {[
                                    [t('Użytkownicy'), report.totals.users],
                                    [t('Sesje'), report.totals.sessions],
                                    [t('Odsłony'), report.totals.views],
                                ].map(([label, value]) => (
                                    <div key={label as string}>
                                        <p className="text-xs text-muted-foreground">
                                            {label}
                                        </p>
                                        <p className="text-2xl font-semibold tracking-tight">
                                            {fmt(value as number)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <svg
                                viewBox={`0 0 ${W} ${H}`}
                                className="h-auto w-full"
                                role="img"
                                aria-label={t('Odsłony dziennie')}
                            >
                                {report.daily.map((d, i) => {
                                    const h = (d.views / max) * (H - 4);

                                    return (
                                        <rect
                                            key={d.date}
                                            x={i * bar + 2}
                                            y={H - h}
                                            width={Math.max(1, bar - 4)}
                                            height={h}
                                            rx="2"
                                            className="fill-foreground"
                                        >
                                            <title>
                                                {`${d.date}: ${d.views}`}
                                            </title>
                                        </rect>
                                    );
                                })}
                            </svg>
                        </div>
                        <div>
                            <p className="mb-2 text-xs font-medium text-muted-foreground">
                                {t('Najpopularniejsze strony')}
                            </p>
                            {report.pages.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('Brak danych.')}
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {report.pages.map((p) => (
                                        <li
                                            key={p.path}
                                            className="flex items-center justify-between gap-3"
                                        >
                                            <span className="truncate">
                                                {p.path}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">
                                                {fmt(p.views)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
