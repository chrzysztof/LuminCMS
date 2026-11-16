import { Loader2, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api } from '@/lib/api';
import { currentLocale, t } from '@/lib/i18n';

type Revision = {
    id: number;
    kind: 'save' | 'publish' | 'autosave';
    author: string | null;
    created_at: string;
};

type Props = {
    open: boolean;
    /** Adres bazowy API buildera tej treści (bez końcowego ukośnika). */
    baseUrl: string;
    onOpenChange: (open: boolean) => void;
    onRestore: (revisionId: number) => Promise<void>;
};

const kindLabel = () => ({
    save: t('Zapis'),
    publish: t('Publikacja'),
    autosave: t('Autozapis'),
});

export const formatRevisionDate = (iso: string) =>
    new Date(iso).toLocaleString(currentLocale(), {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/** Historia wersji strony: ręczne zapisy, publikacje i autozapisy z możliwością przywrócenia. */
export function HistoryDialog({
    open,
    baseUrl,
    onOpenChange,
    onRestore,
}: Props) {
    const [items, setItems] = useState<Revision[] | null>(null);
    const [busy, setBusy] = useState<number | null>(null);

    useEffect(() => {
        if (!open) return;

        let active = true;
        setItems(null);
        api<Revision[]>(`${baseUrl}/revisions`)
            .then((list) => active && setItems(list))
            .catch(() => active && setItems([]));

        return () => {
            active = false;
        };
    }, [open, baseUrl]);

    const restore = async (id: number) => {
        setBusy(id);
        try {
            await onRestore(id);
            onOpenChange(false);
        } finally {
            setBusy(null);
        }
    };

    const labels = kindLabel();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Historia wersji')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Przywrócona wersja trafia do edytora - zapisz ją, aby zastąpiła obecną stronę.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                {items === null ? (
                    <Loader2 className="mx-auto my-6 animate-spin text-muted-foreground" />
                ) : items.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t('Brak zapisanych wersji.')}
                    </p>
                ) : (
                    <ul className="max-h-96 divide-y overflow-y-auto rounded-lg border">
                        {items.map((r) => (
                            <li
                                key={r.id}
                                className="flex items-center justify-between gap-3 px-3 py-2"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {formatRevisionDate(r.created_at)}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {labels[r.kind]}
                                        {r.author ? ` · ${r.author}` : ''}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={busy !== null}
                                    onClick={() => void restore(r.id)}
                                >
                                    {busy === r.id ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <RotateCcw />
                                    )}{' '}
                                    {t('Przywróć')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}
