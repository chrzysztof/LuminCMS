import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cmsUrl } from '@/lib/cms-urls';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    contents_count: number;
    created_at: string;
    is_self: boolean;
};

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    roles: { key: string; label: string }[];
    counts: Record<string, number>;
    filters: { role: string; search: string };
    can: { create: boolean; edit: boolean; delete: boolean };
};

export default function UsersIndex({
    users,
    roles,
    counts,
    filters,
    can,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const visit = (params: Record<string, string | number>) =>
        router.get(
            cmsUrl.userIndex,
            { ...filters, ...params },
            { preserveState: true, replace: true },
        );

    const tabs = [{ key: '', label: t('Wszyscy') }, ...roles];

    return (
        <>
            <Head title={t('Użytkownicy')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Użytkownicy')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('{n} kont', { n: counts.all })}
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={cmsUrl.userCreate}>
                                <Plus /> {t('Dodaj użytkownika')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() =>
                                    visit({ role: tab.key, page: 1 })
                                }
                                className={cn(
                                    'rounded-full px-3 py-1.5 text-sm transition-colors',
                                    filters.role === tab.key
                                        ? 'bg-brand-soft font-medium text-brand-foreground'
                                        : 'text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {tab.label}{' '}
                                <span className="tabular-nums opacity-70">
                                    {counts[tab.key || 'all']}
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
                            placeholder={t('Szukaj imienia lub e-maila…')}
                            className="pl-9"
                        />
                    </form>
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="hidden grid-cols-[1fr_14rem_10rem_7rem_9rem] gap-4 border-b bg-muted/40 px-4 py-2.5 text-xs font-medium text-muted-foreground uppercase md:grid">
                        <span>{t('Nazwa')}</span>
                        <span>{t('E-mail')}</span>
                        <span>{t('Rola')}</span>
                        <span>{t('Treści')}</span>
                        <span>{t('Dodano')}</span>
                    </div>
                    {users.data.length === 0 ? (
                        <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                            {t('Brak wyników.')}
                        </p>
                    ) : (
                        users.data.map((u) => {
                            const cls =
                                'grid gap-1 border-b px-4 py-3 text-sm last:border-b-0 md:grid-cols-[1fr_14rem_10rem_7rem_9rem] md:items-center md:gap-4';
                            const body = (
                                <>
                                    <span className="truncate font-medium">
                                        {u.name}
                                        {u.is_self && (
                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                {t('(Ty)')}
                                            </span>
                                        )}
                                    </span>
                                    <span className="truncate text-muted-foreground">
                                        {u.email}
                                    </span>
                                    <span>
                                        <span className="inline-flex rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-medium text-brand-foreground">
                                            {u.role_label}
                                        </span>
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {u.contents_count}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatDate(u.created_at)}
                                    </span>
                                </>
                            );

                            return can.edit ? (
                                <Link
                                    key={u.id}
                                    href={cmsUrl.userEdit(u.id)}
                                    className={cn(cls, 'hover:bg-muted/40')}
                                >
                                    {body}
                                </Link>
                            ) : (
                                <div key={u.id} className={cls}>
                                    {body}
                                </div>
                            );
                        })
                    )}
                </Card>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2 text-sm">
                        <span className="text-muted-foreground">
                            {t('Strona {a} z {b}', {
                                a: users.current_page,
                                b: users.last_page,
                            })}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={users.current_page <= 1}
                            onClick={() =>
                                visit({ page: users.current_page - 1 })
                            }
                        >
                            {t('Poprzednia')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={users.current_page >= users.last_page}
                            onClick={() =>
                                visit({ page: users.current_page + 1 })
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
