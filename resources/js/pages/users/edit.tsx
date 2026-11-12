import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        contents_count: number;
        is_self: boolean;
    } | null;
    roles: { key: string; label: string; description: string }[];
    defaultRole: string;
    canPromote: boolean;
    canDelete?: boolean;
    others: { id: number; name: string }[];
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function UserEdit({
    user,
    roles,
    defaultRole,
    canPromote,
    canDelete,
    others,
}: Props) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        role: user?.role ?? defaultRole,
    });
    const [reassign, setReassign] = useState('');
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (user) {
            form.put(cmsUrl.userUpdate(user.id), {
                preserveScroll: true,
                onSuccess: () => form.reset('password'),
            });
        } else {
            form.post(cmsUrl.userIndex);
        }
    };

    const remove = () => {
        if (!user || !confirm(t('Usunąć konto „{name}”?', { name: user.name })))
            return;

        router.delete(cmsUrl.userUpdate(user.id), {
            data: { reassign_to: reassign || null },
        });
    };

    const selected = roles.find((r) => r.key === form.data.role);

    return (
        <>
            <Head
                title={user ? t('Edytuj użytkownika') : t('Dodaj użytkownika')}
            />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {user
                            ? t('Użytkownik: {name}', { name: user.name })
                            : t('Dodaj użytkownika')}
                    </h1>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={cmsUrl.userIndex}>
                                {t('Wróć do listy')}
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="animate-spin" />
                            )}
                            {user ? t('Zapisz') : t('Dodaj')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-5 lg:grid-cols-[1fr_22rem]">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Konto')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">
                                    {t('Imię i nazwisko')}
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                                {errors.name && (
                                    <p className="text-xs text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="email">{t('E-mail')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                                {errors.email && (
                                    <p className="text-xs text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="password">
                                    {user ? t('Nowe hasło') : t('Hasło')}
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {user
                                        ? t(
                                              'Zostaw puste, aby nie zmieniać hasła. ',
                                          )
                                        : ''}
                                    {t('Minimum 8 znaków.')}
                                </p>
                                {errors.password && (
                                    <p className="text-xs text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="space-y-5">
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Rola')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <select
                                    id="role"
                                    aria-label={t('Rola')}
                                    className={nativeClass}
                                    disabled={!canPromote}
                                    value={form.data.role}
                                    onChange={(e) =>
                                        form.setData('role', e.target.value)
                                    }
                                >
                                    {roles.map((r) => (
                                        <option key={r.key} value={r.key}>
                                            {r.label}
                                        </option>
                                    ))}
                                </select>
                                {selected && (
                                    <p className="text-xs text-muted-foreground">
                                        {selected.description}
                                    </p>
                                )}
                                {errors.role && (
                                    <p className="text-xs text-destructive">
                                        {errors.role}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {user && canDelete && (
                            <Card className="gap-3">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('Usuwanie konta')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {user.contents_count > 0 && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="reassign">
                                                {t(
                                                    'Przypisz jego treści ({n}) do',
                                                    { n: user.contents_count },
                                                )}
                                            </Label>
                                            <select
                                                id="reassign"
                                                className={nativeClass}
                                                value={reassign}
                                                onChange={(e) =>
                                                    setReassign(e.target.value)
                                                }
                                            >
                                                <option value="">
                                                    {t('— bez autora —')}
                                                </option>
                                                {others.map((o) => (
                                                    <option
                                                        key={o.id}
                                                        value={o.id}
                                                    >
                                                        {o.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={remove}
                                    >
                                        <Trash2 /> {t('Usuń użytkownika')}
                                    </Button>
                                    {errors.user && (
                                        <p className="text-xs text-destructive">
                                            {errors.user}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}
