import { Head, Link } from '@inertiajs/react';
import {
    FileText,
    FilePen,
    CalendarClock,
    Image as ImageIcon,
} from 'lucide-react';
import { StatusBadge } from '@/components/cms/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import type { ContentStatus } from '@/types/cms';
import { t } from '@/lib/i18n';

type Props = {
    stats: {
        published: number;
        drafts: number;
        scheduled: number;
        media: number | null;
    };
    recent: {
        id: number;
        title: string;
        status: ContentStatus;
        type_key: string;
        type_label: string;
        updated_at: string;
    }[];
};

export default function Dashboard({ stats, recent }: Props) {
    const tiles = [
        { label: t('Opublikowane'), value: stats.published, icon: FileText },
        { label: t('Szkice'), value: stats.drafts, icon: FilePen },
        {
            label: t('Zaplanowane'),
            value: stats.scheduled,
            icon: CalendarClock,
        },
        { label: t('Pliki w mediach'), value: stats.media, icon: ImageIcon },
    ].filter((t) => t.value !== null);

    return (
        <>
            <Head title={t('Pulpit')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Pulpit')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('Podsumowanie Twojej witryny')}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {tiles.map(({ label, value, icon: Icon }) => (
                        <Card key={label} className="gap-2 py-5">
                            <CardContent className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {value}
                                    </p>
                                </div>
                                <div className="flex size-10 items-center justify-center rounded-lg bg-brand-soft text-brand-foreground">
                                    <Icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

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
                            <div className="divide-y">
                                {recent.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={cmsUrl.contentEdit(
                                            item.type_key,
                                            item.id,
                                        )}
                                        className="flex items-center justify-between gap-4 py-3 text-sm hover:text-brand-foreground"
                                    >
                                        <span className="min-w-0 truncate font-medium">
                                            {item.title}
                                        </span>
                                        <span className="flex shrink-0 items-center gap-3">
                                            <span className="hidden text-muted-foreground sm:inline">
                                                {item.type_label}
                                            </span>
                                            <StatusBadge status={item.status} />
                                            <span className="hidden w-36 text-right text-muted-foreground md:inline">
                                                {formatDate(item.updated_at)}
                                            </span>
                                        </span>
                                    </Link>
                                ))}
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
