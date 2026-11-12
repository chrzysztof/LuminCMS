import { useState } from 'react';
import { useCan } from '@/lib/can';
import { MediaLibrary } from '@/components/cms/media-library';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { MediaItem } from '@/types/cms';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    multiple?: boolean;
    imagesOnly?: boolean;
    onPick: (items: MediaItem[]) => void;
};

/** Okno wyboru plików z biblioteki mediów (z możliwością wgrania nowych). */
export function MediaPicker({
    open,
    onOpenChange,
    multiple,
    imagesOnly,
    onPick,
}: Props) {
    const [selected, setSelected] = useState<MediaItem[]>([]);
    const can = useCan();

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => {
                if (!o) setSelected([]);
                onOpenChange(o);
            }}
        >
            <DialogContent className="flex h-[80vh] max-w-4xl flex-col">
                <DialogHeader>
                    <DialogTitle>
                        {multiple ? 'Wybierz pliki' : 'Wybierz plik'}
                    </DialogTitle>
                </DialogHeader>
                {!can('upload_files') ? (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        Twoja rola (np. Współpracownik) nie ma dostępu do
                        biblioteki mediów.
                    </p>
                ) : (
                    <MediaLibrary
                        mode="select"
                        multiple={multiple}
                        imagesOnly={imagesOnly}
                        selected={selected}
                        onSelectionChange={setSelected}
                        className="min-h-0 flex-1"
                    />
                )}
                <DialogFooter>
                    <span className="mr-auto self-center text-sm text-muted-foreground">
                        Wybrano: {selected.length}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Anuluj
                    </Button>
                    <Button
                        type="button"
                        disabled={selected.length === 0}
                        onClick={() => {
                            onPick(selected);
                            setSelected([]);
                            onOpenChange(false);
                        }}
                    >
                        Użyj wybranych
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
