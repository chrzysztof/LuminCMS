import { Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { t } from '@/lib/i18n';

type Shortcode = { name: string; usage: string; description: string };

export default function ShortcodesIndex({
    shortcodes,
}: {
    shortcodes: Shortcode[];
}) {
    const [copied, setCopied] = useState<string | null>(null);

    // Pierwszy zapis z "a / b" kopiujemy jako pierwszy wariant.
    const copy = async (sc: Shortcode) => {
        try {
            await navigator.clipboard.writeText(sc.usage.split(' / ')[0]);
            setCopied(sc.name);
            setTimeout(() => setCopied(null), 1500);
        } catch {
            /* schowek niedostępny (np. HTTP) */
        }
    };

    return (
        <>
            <Head title={t('Shortcody')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Shortcody')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Wklej shortcode w treść strony lub w pole edytora tekstu, a w publicznej wersji zostanie zamieniony na gotowy element. Zapis [[nazwa]] wypisuje go dosłownie.',
                        )}
                    </p>
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    {shortcodes.length === 0 ? (
                        <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                            {t('Brak shortcodów.')}
                        </p>
                    ) : (
                        shortcodes.map((sc) => (
                            <div
                                key={sc.name}
                                className="grid gap-2 border-b px-4 py-3 last:border-b-0 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-start md:gap-6"
                            >
                                <div className="flex min-w-0 items-start gap-2">
                                    <code className="min-w-0 flex-1 rounded bg-muted px-2 py-1 text-xs break-all">
                                        {sc.usage}
                                    </code>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Kopiuj')}
                                        onClick={() => copy(sc)}
                                    >
                                        {copied === sc.name ? (
                                            <Check />
                                        ) : (
                                            <Copy />
                                        )}
                                    </Button>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {sc.description}
                                </p>
                            </div>
                        ))
                    )}
                </Card>
            </div>
        </>
    );
}
