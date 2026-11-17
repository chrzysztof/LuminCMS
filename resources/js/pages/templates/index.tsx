import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Slot = {
    key: string;
    kind: string;
    label: string;
    group: string;
    content_id: number | null;
};
type Part = { id: number; title: string };

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function TemplatesIndex({
    slots,
    parts,
    partType,
}: {
    slots: Slot[];
    parts: Part[];
    partType: string;
}) {
    const groups = [...new Set(slots.map((s) => s.group))];

    const assign = (key: string, value: string) =>
        router.put(
            cmsUrl.templates,
            { key, content_id: value === '' ? null : Number(value) },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('Szablony')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:px-6 md:pt-5 md:pb-6">
                <div>
                    <h1 className="text-[26px] leading-tight font-bold tracking-tight">
                        {t('Szablony')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Zaprojektuj w edytorze wizualnym wygląd pojedynczych treści, archiwów i stron kategorii. Bez szablonu używany jest domyślny widok motywu. Treść wpisu i listę wstawiasz blokami z kategorii „Szablony”, a tytuł, zajawkę i obraz wyróżniający powiązaniem elementu z polem.',
                        )}
                    </p>
                </div>
                {groups.map((group) => (
                    <section key={group} className="space-y-3">
                        <h2 className="text-lg font-semibold">{t(group)}</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {slots
                                .filter((s) => s.group === group)
                                .map((s) => (
                                    <Card key={s.key}>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                {s.label}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <select
                                                className={nativeClass}
                                                aria-label={s.label}
                                                value={s.content_id ?? ''}
                                                onChange={(e) =>
                                                    assign(
                                                        s.key,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    {t('Domyślny widok motywu')}
                                                </option>
                                                {parts.map((p) => (
                                                    <option
                                                        key={p.id}
                                                        value={p.id}
                                                    >
                                                        {p.title}
                                                    </option>
                                                ))}
                                            </select>
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
                                                        router.post(
                                                            cmsUrl.templates,
                                                            { key: s.key },
                                                        )
                                                    }
                                                >
                                                    <Plus />
                                                    {t('Utwórz szablon')}
                                                </Button>
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}
