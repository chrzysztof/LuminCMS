import { useEffect, useState } from 'react';
import type { CustomField } from '@/components/cms/builder/field-binding';
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
    fields: customFields = [],
    onApply,
    onClose,
}: {
    block: ThemeBlock;
    values: Values;
    fields?: CustomField[];
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
                <BlockBindings
                    block={block}
                    fields={customFields}
                    bindings={(values.__bind as Record<string, string>) ?? {}}
                    onChange={(bindings) =>
                        setValues((v) => ({ ...v, __bind: bindings }))
                    }
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

/** Które pola treści pasują do pola bloku danego typu. */
const COMPATIBLE: Record<string, string[]> = {
    text: [
        'text',
        'textarea',
        'wysiwyg',
        'number',
        'select',
        'email',
        'url',
        'date',
    ],
    textarea: ['text', 'textarea', 'wysiwyg'],
    url: ['url', 'link'],
    image: ['image'],
    number: ['number'],
};

/** Powiązanie pól bloku z polami własnymi treści: wartość pola zastępuje ustawienie bloku na stronie, a puste pole zostawia ustawienie. */
function BlockBindings({
    block,
    fields,
    bindings,
    onChange,
}: {
    block: ThemeBlock;
    fields: CustomField[];
    bindings: Record<string, string>;
    onChange: (bindings: Record<string, string>) => void;
}) {
    const targets = block.fields.filter((f) => COMPATIBLE[f.type]);

    if (targets.length === 0 || fields.length === 0) return null;

    return (
        <details
            className="rounded-md border p-3"
            open={Object.keys(bindings).length > 0}
        >
            <summary className="cursor-pointer text-sm font-medium">
                {t('Pola własne tej treści')}
            </summary>
            <p className="mt-2 mb-2 text-xs text-muted-foreground">
                {t(
                    'Wybrane pole zastąpi ustawienie bloku na stronie; gdy pole jest puste, zostanie ustawienie z góry. W podglądzie edytora widać ustawienie bloku.',
                )}
            </p>
            <div className="grid gap-2 sm:grid-cols-2">
                {targets.map((target) => (
                    <label
                        key={target.name}
                        className="text-xs text-muted-foreground"
                    >
                        {target.label}
                        <select
                            className="mt-1 flex w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm text-foreground"
                            value={bindings[target.name] ?? ''}
                            onChange={(e) => {
                                const next = { ...bindings };
                                if (e.target.value)
                                    next[target.name] = e.target.value;
                                else delete next[target.name];
                                onChange(next);
                            }}
                        >
                            <option value="">{t('Bez powiązania')}</option>
                            {fields
                                .filter((f) =>
                                    COMPATIBLE[target.type].includes(f.type),
                                )
                                .map((f) => (
                                    <option key={f.name} value={f.name}>
                                        {f.label}
                                    </option>
                                ))}
                        </select>
                    </label>
                ))}
            </div>
        </details>
    );
}
