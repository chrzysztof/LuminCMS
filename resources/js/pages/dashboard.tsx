import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    FilePen,
    FileText,
    Image as ImageIcon,
    Palette,
    Plus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { StatusBadge } from '@/components/cms/status-badge';
import { AnalyticsWidget } from '@/components/cms/analytics-widget';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { formatDate } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { ContentStatus } from '@/types/cms';

type Props = {
    stats: {
        published: number;
        drafts: number;
        scheduled: number;
        media: number | null;
    };
    activity: { month: string; created: number; published: number }[];
    new_30d: number;
    analytics: boolean;
    quick: {
        pages: boolean;
        posts: boolean;
        media: boolean;
        customize: boolean;
    };
    recent: {
        id: number;
        title: string;
        status: ContentStatus;
        type_key: string;
        type_label: string;
        author: string | null;
        updated_at: string;
    }[];
};

const W = 640;
const H = 200;
const PAD = { l: 28, r: 8, t: 10, b: 24 };

/** Gładka krzywa przez punkty (Catmull-Rom → Bézier). */
function smooth(
    points: [number, number][],
    min = -Infinity,
    max = Infinity,
): string {
    const clamp = (v: number) => Math.min(max, Math.max(min, v));

    return points
        .map((p, i) => {
            if (i === 0) return `M${p[0]},${p[1]}`;
            const p0 = points[i - 2] ?? points[i - 1];
            const p1 = points[i - 1];
            const p3 = points[i + 1] ?? p;
            const c1 = [p1[0] + (p[0] - p0[0]) / 6, p1[1] + (p[1] - p0[1]) / 6];
            const c2 = [p[0] - (p3[0] - p1[0]) / 6, p[1] - (p3[1] - p1[1]) / 6];

            return `C${c1[0]},${clamp(c1[1])} ${c2[0]},${clamp(c2[1])} ${p[0]},${p[1]}`;
        })
        .join(' ');
}

function ActivityChart({ data }: { data: Props['activity'] }) {
    const max = Math.max(4, ...data.flatMap((d) => [d.created, d.published]));
    const x = (i: number) =>
        PAD.l + (i * (W - PAD.l - PAD.r)) / Math.max(1, data.length - 1);
    const y = (v: number) => PAD.t + (1 - v / max) * (H - PAD.t - PAD.b);
    const line = (key: 'created' | 'published') =>
        data.map((d, i): [number, number] => [x(i), y(d[key])]);
    const ticks = [0, 0.5, 1].map((f) => Math.round(max * f));
    const lang = document.documentElement.lang || undefined;
    const published = line('published');

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            className="h-auto w-full"
            role="img"
            aria-label={t('Aktywność w ostatnich miesiącach')}
        >
            {ticks.map((v) => (
                <g key={v}>
                    <line
                        x1={PAD.l}
                        x2={W - PAD.r}
                        y1={y(v)}
                        y2={y(v)}
                        className="stroke-border"
                        strokeDasharray="3 4"
                    />
                    <text
                        x={PAD.l - 6}
                        y={y(v) + 3}
                        textAnchor="end"
                        className="fill-muted-foreground text-[10px]"
                    >
                        {v}
                    </text>
                </g>
            ))}
            <path
                d={`${smooth(published, y(max), y(0))} L${x(data.length - 1)},${y(0)} L${x(0)},${y(0)} Z`}
                className="fill-foreground/5"
            />
            <path
                d={smooth(line('created'), y(max), y(0))}
                fill="none"
                className="stroke-muted-foreground"
                strokeWidth="1.5"
                strokeDasharray="4 4"
            />
            <path
                d={smooth(published, y(max), y(0))}
                fill="none"
                className="stroke-foreground"
                strokeWidth="2"
            />
            {published.map(([px, py], i) => (
                <circle
                    key={data[i].month}
                    cx={px}
                    cy={py}
                    r="3"
                    className="fill-background stroke-foreground"
                    strokeWidth="1.5"
                />
            ))}
            {data.map((d, i) => (
                <text
                    key={d.month}
                    x={x(i)}
                    y={H - 6}
                    textAnchor="middle"
                    className="fill-muted-foreground text-[10px]"
                >
                    {new Date(`${d.month}-01`).toLocaleDateString(lang, {
                        month: 'short',
                    })}
                </text>
            ))}
        </svg>
    );
}

export default function Dashboard({
    stats,
    activity,
    new_30d,
    analytics,
    quick,
    recent,
}: Props) {
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;
    const first = auth.user.name.split(' ')[0];

    const tiles = [
        {
            label: t('Opublikowane'),
            value: stats.published,
            icon: FileText,
            hint: t('+{n} w ostatnich 30 dniach', { n: new_30d }),
        },
        { label: t('Szkice'), value: stats.drafts, icon: FilePen },
        {
            label: t('Zaplanowane'),
            value: stats.scheduled,
            icon: CalendarClock,
        },
        { label: t('Pliki w mediach'), value: stats.media, icon: ImageIcon },
    ].filter((tile) => tile.value !== null);

    const actions: { label: string; href: string; icon: LucideIcon }[] = [
        ...(quick.pages
            ? [
                  {
                      label: t('Nowa strona'),
                      href: cmsUrl.contentCreate('page'),
                      icon: Plus,
                  },
              ]
            : []),
        ...(quick.posts
            ? [
                  {
                      label: t('Nowy wpis'),
                      href: cmsUrl.contentCreate('post'),
                      icon: Plus,
                  },
              ]
            : []),
        ...(quick.media
            ? [
                  {
                      label: t('Dodaj pliki do mediów'),
                      href: '/cms/media',
                      icon: ImageIcon,
                  },
              ]
            : []),
        ...(quick.customize
            ? [
                  {
                      label: t('Dopasuj wygląd'),
                      href: cmsUrl.customize,
                      icon: Palette,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={t('Pulpit')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Witaj ponownie, {name}!', { name: first })}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('Podsumowanie Twojej witryny')}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {tiles.map(({ label, value, icon: Icon, hint }, i) => (
                        <Card
                            key={label}
                            className={cn(
                                'gap-2 py-5',
                                i === 0 &&
                                    'border-transparent bg-primary text-primary-foreground',
                            )}
                        >
                            <CardContent className="flex items-start justify-between">
                                <div>
                                    <p
                                        className={cn(
                                            'text-sm',
                                            i === 0
                                                ? 'opacity-70'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {label}
                                    </p>
                                    <p className="mt-1 text-3xl font-semibold tabular-nums">
                                        {value}
                                    </p>
                                    {hint && (
                                        <p className="mt-1 text-xs opacity-70">
                                            {hint}
                                        </p>
                                    )}
                                </div>
                                <div
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-lg',
                                        i === 0
                                            ? 'bg-primary-foreground/15'
                                            : 'bg-muted',
                                    )}
                                >
                                    <Icon className="size-4" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-base">
                                {t('Aktywność w czasie')}
                            </CardTitle>
                            <div className="flex items-center gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span className="h-0.5 w-4 bg-foreground" />
                                    {t('Publikacje')}
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="h-0 w-4 border-t border-dashed border-muted-foreground" />
                                    {t('Nowe treści')}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ActivityChart data={activity} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Szybkie akcje')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {actions.map(({ label, href, icon: Icon }) => (
                                <Link
                                    key={label}
                                    href={href}
                                    className="flex items-center gap-3 rounded-lg border p-3 text-sm font-medium transition-colors hover:bg-muted"
                                >
                                    <span className="flex size-8 items-center justify-center rounded-md bg-muted">
                                        <Icon className="size-4" />
                                    </span>
                                    {label}
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                {analytics && <AnalyticsWidget />}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Ostatnio edytowane')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t(
                                    'Nie masz jeszcze żadnych treści. Dodaj pierwszą stronę z menu po lewej.',
                                )}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs text-muted-foreground uppercase">
                                            <th className="pb-2 font-medium">
                                                {t('Nazwa')}
                                            </th>
                                            <th className="hidden pb-2 font-medium sm:table-cell">
                                                {t('Typ')}
                                            </th>
                                            <th className="hidden pb-2 font-medium md:table-cell">
                                                {t('Autor')}
                                            </th>
                                            <th className="pb-2 font-medium">
                                                {t('Status')}
                                            </th>
                                            <th className="hidden pb-2 text-right font-medium md:table-cell">
                                                {t('Zmieniono')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {recent.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="hover:bg-muted/50"
                                            >
                                                <td className="py-3 pr-4">
                                                    <Link
                                                        href={cmsUrl.contentEdit(
                                                            item.type_key,
                                                            item.id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {item.title}
                                                    </Link>
                                                </td>
                                                <td className="hidden py-3 pr-4 text-muted-foreground sm:table-cell">
                                                    {item.type_label}
                                                </td>
                                                <td className="hidden py-3 pr-4 text-muted-foreground md:table-cell">
                                                    {item.author ?? '—'}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <StatusBadge
                                                        status={item.status}
                                                    />
                                                </td>
                                                <td className="hidden py-3 text-right text-muted-foreground md:table-cell">
                                                    {formatDate(
                                                        item.updated_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: t('Pulpit'), href: dashboard() }],
};
