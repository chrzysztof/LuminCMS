import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { StatusBadge } from '@/components/cms/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cmsUrl } from '@/lib/cms-urls';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ContentStatus, ContentTypeInfo } from '@/types/cms';
import { t } from '@/lib/i18n';

type Item = {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    can_edit: boolean;
    can_delete: boolean;
    author: string | null;
    updated_at: string;
};

type Props = {
    type: ContentTypeInfo;
    items: {
        data: Item[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: Record<'all' | ContentStatus, number>;
    filters: {
        status: string;
        search: string;
        author: number | '';
        terms: Record<string, number>;
    };
    authors: { id: number; name: string }[];
    taxonomies: {
        key: string;
        label: string;
        terms: { id: number; name: string; depth: number }[];
    }[];
};

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const tabs: {
    key: '' | ContentStatus;
    label: string;
    count: 'all' | ContentStatus;
}[] = [
    { key: '', label: t('Wszystkie'), count: 'all' },
    { key: 'published', label: t('Opublikowane'), count: 'published' },
    { key: 'draft', label: t('Szkice'), count: 'draft' },
    { key: 'pending', label: t('Do przeglądu'), count: 'pending' },
    { key: 'scheduled', label: t('Zaplanowane'), count: 'scheduled' },
];

function Row({
    href,
    className,
    children,
}: {
    href: string | null;
    className: string;
    children: ReactNode;
}) {
    return href ? (
        <Link href={href} className={className}>
            {children}
        </Link>
    ) : (
        <div className={cn(className, 'opacity-70')}>{children}</div>
    );
}

export default function ContentIndex({
    type,
    items,
    counts,
    filters,
    authors,
    taxonomies,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const setTerm = (key: string, id: string) => {
        const terms = { ...filters.terms };
        if (id) terms[key] = Number(id);
        else delete terms[key];
        visit({ terms, page: 1 });
    };
    const hasFilters =
        filters.author !== '' || Object.keys(filters.terms).length > 0;

    const visit = (params: Record<string, unknown>) =>
        router.get(
            cmsUrl.contentIndex(type.key),
            { ...filters, ...params },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={type.label_plural} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {type.label_plural}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('{n} pozycji', { n: counts.all })}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={cmsUrl.contentCreate(type.key)}>
                            <Plus />{' '}
                            {t('Dodaj: {type}', {
                                type: type.label.toLowerCase(),
                            })}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() =>
                                    visit({ status: tab.key, page: 1 })
                                }
                                className={cn(
                                    'rounded-full px-3 py-1.5 text-sm transition-colors',
                                    filters.status === tab.key
                                        ? 'bg-brand-soft font-medium text-brand-foreground'
                                        : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {tab.label}{' '}
                                <span className="tabular-nums opacity-70">
                                    {counts[tab.count]}
                                </span>
                            </button>
                        ))}
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            visit({ search, page: 1 });
                        }}
                        className="relative w-full sm:w-64"
                    >
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Szukaj po tytule…')}
                            className="pl-9"
                        />
                    </form>
                </div>

                {(taxonomies.length > 0 || authors.length > 1) && (
                    <div className="flex flex-wrap items-center gap-2">
                        {taxonomies.map((tx) => (
                            <select
                                key={tx.key}
                                aria-label={tx.label}
                                className={selectClass}
                                value={filters.terms[tx.key] ?? ''}
                                onChange={(e) =>
                                    setTerm(tx.key, e.target.value)
                                }
                            >
                                <option value="">
                                    {t('{taxonomy}: wszystkie', {
                                        taxonomy: tx.label,
                                    })}
                                </option>
                                {tx.terms.map((term) => (
                                    <option key={term.id} value={term.id}>
                                        {'— '.repeat(term.depth)}
                                        {term.name}
                                    </option>
                                ))}
                            </select>
                        ))}
                        {authors.length > 1 && (
                            <select
                                aria-label={t('Autor')}
                                className={selectClass}
                                value={filters.author}
                                onChange={(e) =>
                                    visit({ author: e.target.value, page: 1 })
                                }
                            >
                                <option value="">{t('Autor: wszyscy')}</option>
                                {authors.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    visit({ author: '', terms: {}, page: 1 })
                                }
                            >
                                {t('Wyczyść filtry')}
                            </Button>
                        )}
                    </div>
                )}

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="hidden grid-cols-[1fr_9rem_9rem_11rem] gap-4 border-b bg-muted/40 px-4 py-2.5 text-xs font-medium text-muted-foreground uppercase md:grid">
                        <span>{t('Tytuł')}</span>
                        <span>{t('Autor')}</span>
                        <span>{t('Status')}</span>
                        <span>{t('Zmieniono')}</span>
                    </div>
                    {items.data.length === 0 ? (
                        <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                            {t('Brak wyników.')}
                        </p>
                    ) : (
                        items.data.map((item) => (
                            <Row
                                key={item.id}
                                href={
                                    item.can_edit
                                        ? cmsUrl.contentEdit(type.key, item.id)
                                        : null
                                }
                                className="grid gap-1 border-b px-4 py-3 text-sm last:border-b-0 hover:bg-muted/40 md:grid-cols-[1fr_9rem_9rem_11rem] md:items-center md:gap-4"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">
                                        {item.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        /
                                        {type.url_prefix
                                            ? `${type.url_prefix}/`
                                            : ''}
                                        {item.slug}
                                    </span>
                                </span>
                                <span className="truncate text-muted-foreground">
                                    {item.author ?? '—'}
                                </span>
                                <span>
                                    <StatusBadge status={item.status} />
                                </span>
                                <span className="text-muted-foreground">
                                    {formatDate(item.updated_at)}
                                </span>
                            </Row>
                        ))
                    )}
                </Card>

                {items.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2 text-sm">
                        <span className="text-muted-foreground">
                            {t('Strona {a} z {b}', {
                                a: items.current_page,
                                b: items.last_page,
                            })}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={items.current_page <= 1}
                            onClick={() =>
                                visit({ page: items.current_page - 1 })
                            }
                        >
                            {t('Poprzednia')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={items.current_page >= items.last_page}
                            onClick={() =>
                                visit({ page: items.current_page + 1 })
                            }
                        >
                            {t('Następna')}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
