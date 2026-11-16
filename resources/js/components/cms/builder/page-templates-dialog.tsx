import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    PAGE_TEMPLATES,
    SECTIONS,
    pageHtml,
    type PageTemplate,
} from '@/lib/builder/sections';
import { t } from '@/lib/i18n';

type Props = {
    open: boolean;
    /** Czy strona ma już jakąś treść (wtedy zastąpienie wymaga potwierdzenia). */
    hasContent: boolean;
    onOpenChange: (open: boolean) => void;
    onInsert: (html: string, mode: 'replace' | 'append') => void;
};

/** Biblioteka gotowych stron: złożone z sekcji, wstawiane na koniec albo w miejsce treści. */
export function PageTemplatesDialog({
    open,
    hasContent,
    onOpenChange,
    onInsert,
}: Props) {
    const [confirming, setConfirming] = useState<PageTemplate | null>(null);
    const label = (id: string) =>
        t(SECTIONS.find((s) => s.id === id)?.label ?? id);

    const close = (v: boolean) => {
        if (!v) setConfirming(null);
        onOpenChange(v);
    };

    const insert = (tpl: PageTemplate, mode: 'replace' | 'append') => {
        onInsert(pageHtml(tpl), mode);
        close(false);
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('Biblioteka stron')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Wybierz gotowy układ. Wszystkie sekcje możesz potem edytować, usuwać i przestawiać.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3 sm:grid-cols-2">
                    {PAGE_TEMPLATES.map((tpl) => (
                        <div
                            key={tpl.id}
                            className="flex flex-col rounded-xl border p-4"
                        >
                            <h3 className="font-semibold">{t(tpl.label)}</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(tpl.description)}
                            </p>
                            <ol className="mt-3 flex-1 space-y-1 text-xs text-muted-foreground">
                                {tpl.sections.map((id, i) => (
                                    <li key={`${id}-${i}`}>
                                        {i + 1}. {label(id)}
                                    </li>
                                ))}
                            </ol>
                            {confirming?.id === tpl.id ? (
                                <div className="mt-4 space-y-2">
                                    <p className="text-xs text-destructive">
                                        {t(
                                            'Obecna treść strony zostanie zastąpiona. Możesz to cofnąć w edytorze.',
                                        )}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                insert(tpl, 'replace')
                                            }
                                        >
                                            {t('Zastąp')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setConfirming(null)}
                                        >
                                            {t('Anuluj')}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            hasContent
                                                ? setConfirming(tpl)
                                                : insert(tpl, 'replace')
                                        }
                                    >
                                        {hasContent
                                            ? t('Zastąp treść')
                                            : t('Użyj układu')}
                                    </Button>
                                    {hasContent && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                insert(tpl, 'append')
                                            }
                                        >
                                            {t('Dołącz na końcu')}
                                        </Button>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
