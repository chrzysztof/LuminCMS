import { Head, router, usePage } from '@inertiajs/react';
import { Download, Loader2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type FontRow = { family: string; slug: string; weights: number[] };

const inputClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function FontsIndex({
    fonts,
    css,
    weights,
}: {
    fonts: FontRow[];
    css: string;
    weights: number[];
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [family, setFamily] = useState('');
    const [selected, setSelected] = useState<number[]>([400, 700]);
    const [busy, setBusy] = useState(false);

    const toggle = (w: number) =>
        setSelected((s) =>
            s.includes(w) ? s.filter((x) => x !== w) : [...s, w],
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        router.post(
            cmsUrl.fonts,
            { family, weights: selected },
            {
                preserveScroll: true,
                onSuccess: () => setFamily(''),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <>
            <Head title={t('Czcionki')} />
            {/* Reguły @font-face importowanych czcionek, żeby pokazać próbki. */}
            <style>{css}</style>
            <div className="flex flex-1 flex-col gap-5 p-4 md:px-6 md:pt-5 md:pb-6">
                <div>
                    <h1 className="text-[26px] leading-tight font-bold tracking-tight">
                        {t('Czcionki')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Zaimportuj czcionkę z Google Fonts. Pliki są pobierane na Twój serwer i stamtąd serwowane, więc odwiedzający nie łączą się z Google. Zaimportowaną czcionkę wybierzesz w Wygląd → Dostosuj (Krój pisma).',
                        )}
                    </p>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Importuj czcionkę')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label
                                    htmlFor="font-family"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    {t('Nazwa czcionki')}
                                </label>
                                <input
                                    id="font-family"
                                    className={inputClass}
                                    value={family}
                                    onChange={(e) => setFamily(e.target.value)}
                                    placeholder="Poppins"
                                    maxLength={60}
                                    required
                                />
                                {errors.family && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {errors.family}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'Dokładna nazwa ze strony fonts.google.com, np. Poppins, Playfair Display, Open Sans.',
                                    )}
                                </p>
                            </div>
                            <fieldset>
                                <legend className="mb-1 text-sm font-medium">
                                    {t('Grubości')}
                                </legend>
                                <div className="flex flex-wrap gap-3">
                                    {weights.map((w) => (
                                        <label
                                            key={w}
                                            className="flex items-center gap-1.5 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(w)}
                                                onChange={() => toggle(w)}
                                            />
                                            {w}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <Button
                                type="submit"
                                disabled={
                                    busy ||
                                    family.trim() === '' ||
                                    selected.length === 0
                                }
                            >
                                {busy ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Download />
                                )}{' '}
                                {t('Importuj')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('Zaimportowane czcionki')}
                    </h2>
                    {fonts.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('Nie zaimportowano jeszcze żadnej czcionki.')}
                        </p>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        {fonts.map((f) => (
                            <Card key={f.slug}>
                                <CardHeader className="flex-row items-center justify-between gap-2">
                                    <CardTitle className="text-base">
                                        {f.family}
                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                            {f.weights.join(', ')}
                                        </span>
                                    </CardTitle>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Usuń')}
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    t(
                                                        'Usunąć czcionkę {name}? Strony używające jej wrócą do czcionki zastępczej.',
                                                        { name: f.family },
                                                    ),
                                                )
                                            ) {
                                                router.delete(
                                                    cmsUrl.fontRemove(f.slug),
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 />
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <p
                                        className="text-2xl"
                                        style={{
                                            fontFamily: `'${f.family}', system-ui, sans-serif`,
                                        }}
                                    >
                                        Zażółć gęślą jaźń 0123
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
