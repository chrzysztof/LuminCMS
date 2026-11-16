import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { SavedBlock } from '@/lib/builder/saved-blocks';
import { t } from '@/lib/i18n';

type SaveProps = {
    open: boolean;
    defaultName: string;
    saving: boolean;
    onOpenChange: (open: boolean) => void;
    onSave: (name: string) => void;
};

/** Pytanie o nazwę zapisywanego bloku. */
export function SaveBlockDialog({
    open,
    defaultName,
    saving,
    onOpenChange,
    onSave,
}: SaveProps) {
    const [name, setName] = useState(defaultName);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (name.trim()) onSave(name.trim());
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>{t('Zapisz jako blok')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Blok trafi do kategorii „Moje bloki” i będzie dostępny na każdej stronie.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Input
                        autoFocus
                        value={name}
                        maxLength={100}
                        placeholder={t('Nazwa bloku')}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Anuluj')}
                        </Button>
                        <Button type="submit" disabled={!name.trim() || saving}>
                            {t('Zapisz')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type ManageProps = {
    open: boolean;
    blocks: SavedBlock[];
    onOpenChange: (open: boolean) => void;
    onDelete: (block: SavedBlock) => void;
};

/** Lista własnych bloków z możliwością usuwania. */
export function ManageBlocksDialog({
    open,
    blocks,
    onOpenChange,
    onDelete,
}: ManageProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Moje bloki')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Usunięcie bloku nie zmienia stron, na których został już użyty.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                {blocks.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        {t(
                            'Nie masz jeszcze własnych bloków. Zaznacz element na stronie i wybierz „Zapisz jako blok”.',
                        )}
                    </p>
                ) : (
                    <ul className="max-h-80 divide-y overflow-y-auto rounded-lg border">
                        {blocks.map((b) => (
                            <li
                                key={b.id}
                                className="flex items-center justify-between gap-2 px-3 py-2"
                            >
                                <span className="truncate text-sm">
                                    {b.name}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 text-destructive"
                                    aria-label={t('Usuń')}
                                    title={t('Usuń')}
                                    onClick={() => onDelete(b)}
                                >
                                    <Trash2 />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}
