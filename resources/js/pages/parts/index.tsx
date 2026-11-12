import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Slot = { slot: string; label: string; content_id: number | null };
type Part = { id: number; title: string; slots: string[] };

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function PartsIndex({
    slots,
    parts,
    partType,
}: {
    slots: Slot[];
    parts: Part[];
    partType: string;
}) {
    const [title, setTitle] = useState('');

    const assign = (slot: string, value: string) =>
        router.put(
            cmsUrl.partAssign(slot),
            { content_id: value === '' ? null : Number(value) },
            { preserveScroll: true },
        );

    const create = (name: string, slot?: string) =>
        router.post(cmsUrl.parts, { title: name, slot: slot ?? null });

    const remove = (part: Part) => {
        if (confirm(t('Usunąć część „{name}”?', { name: part.title })))
            router.delete(cmsUrl.partDelete(part.id), {
                preserveScroll: true,
            });
    };

    return (
        <>
            <Head title={t('Części')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Części')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Nagłówek, stopka i inne części wspólne dla całej witryny edytujesz w edytorze wizualnym. Slot bez przypisanej części używa domyślnego wyglądu motywu.',
                        )}
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {slots.map((s) => (
                        <Card key={s.slot}>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t(s.label)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <select
                                    className={nativeClass}
                                    aria-label={t(s.label)}
                                    value={s.content_id ?? ''}
                                    onChange={(e) =>
                                        assign(s.slot, e.target.value)
                                    }
                                >
                                    <option value="">
                                        {t('Domyślny wygląd motywu')}
                                    </option>
                                    {parts.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title}
                                        </option>
                                    ))}
                                </select>
                                <div className="flex flex-wrap gap-2">
                                    {s.content_id ? (
                                        <Button asChild size="sm">
                                            <Link
                                                href={cmsUrl.contentBuilder(
                                                    partType,
                                                    s.content_id,
                                                )}
                                            >
                                                <Pencil />
                                                {t('Edytuj w edytorze')}
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                create(t(s.label), s.slot)
                                            }
                                        >
                                            <Plus />
                                            {t('Utwórz własną część')}
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Wszystkie części')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <form
                            className="flex gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (title.trim()) create(title.trim());
                            }}
                        >
                            <Input
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder={t('Nazwa nowej części')}
                                aria-label={t('Nazwa nowej części')}
                                maxLength={150}
                            />
                            <Button type="submit" disabled={!title.trim()}>
                                <Plus />
                                {t('Dodaj')}
                            </Button>
                        </form>
                        {parts.length === 0 ? (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                {t('Brak części.')}
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {parts.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex items-center gap-3 px-3 py-2"
                                    >
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {p.title}
                                        </span>
                                        {p.slots.length > 0 && (
                                            <span className="text-xs text-muted-foreground">
                                                {t('Slot')}:{' '}
                                                {p.slots
                                                    .map((k) =>
                                                        t(
                                                            slots.find(
                                                                (s) =>
                                                                    s.slot ===
                                                                    k,
                                                            )?.label ?? k,
                                                        ),
                                                    )
                                                    .join(', ')}
                                            </span>
                                        )}
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={cmsUrl.contentBuilder(
                                                    partType,
                                                    p.id,
                                                )}
                                            >
                                                <Pencil />
                                                {t('Edytuj')}
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Usuń')}
                                            onClick={() => remove(p)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
