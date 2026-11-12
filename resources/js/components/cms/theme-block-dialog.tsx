import { useEffect, useState } from 'react';
import { FieldList } from '@/components/cms/fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api } from '@/lib/api';
import type { ThemeBlock } from '@/lib/builder/theme-blocks';
import { t } from '@/lib/i18n';
import type { MediaItem } from '@/types/cms';

type Values = Record<string, unknown>;

/** Ustawienia bloku motywu: ten sam formularz pól co w polach własnych (FieldList). */
export function ThemeBlockDialog({
    block,
    values: initial,
    onApply,
    onClose,
}: {
    block: ThemeBlock;
    values: Values;
    onApply: (values: Values) => void;
    onClose: () => void;
}) {
    const [values, setValues] = useState<Values>(initial);
    const [media, setMedia] = useState<Record<number, MediaItem>>({});

    // Obiekty mediów dla zapisanych identyfikatorów (podgląd w polach obrazów).
    useEffect(() => {
        api<{ media: Record<number, MediaItem> }>('/cms/blocks/preview', {
            method: 'POST',
            body: { block: block.name, attrs: initial },
        })
            .then((r) => setMedia(r.media))
            .catch(() => undefined);
    }, [block.name, initial]);

    const rememberMedia = (items: MediaItem[]) =>
        setMedia((m) => ({
            ...m,
            ...Object.fromEntries(items.map((i) => [i.id, i])),
        }));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{block.label}</DialogTitle>
                </DialogHeader>
                <FieldList
                    fields={block.fields}
                    values={values}
                    errors={{}}
                    media={media}
                    rememberMedia={rememberMedia}
                    onChange={(name, value) =>
                        setValues((v) => ({ ...v, [name]: value }))
                    }
                    errorKey="attrs"
                />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('Anuluj')}
                    </Button>
                    <Button type="button" onClick={() => onApply(values)}>
                        {t('Zastosuj')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
