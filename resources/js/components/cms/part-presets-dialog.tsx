import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

export type PartPreset = {
    slot: string;
    key: string;
    label: string;
    description: string;
};

type Props = {
    slotLabel: string;
    presets: PartPreset[];
    onClose: () => void;
};

/** Podgląd szerokości 1200 px skalowany do szerokości karty, o wysokości dopasowanej do treści. */
const FRAME_WIDTH = 1200;

function PresetPreview({ preset }: { preset: PartPreset }) {
    const box = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.2);
    const [contentHeight, setContentHeight] = useState(300);

    useEffect(() => {
        const el = box.current;
        if (!el) return;

        const update = () => setScale(el.clientWidth / FRAME_WIDTH);
        update();
        const observer = new ResizeObserver(update);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={box}
            className="relative w-full overflow-hidden border-b bg-white"
            style={{ height: contentHeight * scale }}
        >
            <iframe
                title={preset.label}
                src={cmsUrl.partPresetPreview(preset.slot, preset.key)}
                // Bez skryptów; allow-same-origin pozwala tylko zmierzyć wysokość treści.
                sandbox="allow-same-origin"
                tabIndex={-1}
                onLoad={(e) => {
                    const body = e.currentTarget.contentDocument?.body;
                    if (body) setContentHeight(Math.max(60, body.offsetHeight));
                }}
                className="pointer-events-none absolute top-0 left-0 border-0"
                style={{
                    width: FRAME_WIDTH,
                    height: contentHeight,
                    transform: `scale(${scale})`,
                    transformOrigin: 'top left',
                }}
            />
        </div>
    );
}

/** Wybór gotowego projektu nagłówka lub stopki z podglądem na żywo (motyw, menu i ustawienia witryny). */
export function PartPresetsDialog({ slotLabel, presets, onClose }: Props) {
    const [busy, setBusy] = useState<string | null>(null);

    const use = (preset: PartPreset) => {
        setBusy(preset.key);
        router.post(
            cmsUrl.partPresets,
            { slot: preset.slot, key: preset.key },
            { onError: () => setBusy(null) },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('Gotowe projekty: {name}', { name: slotLabel })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Wybierz projekt jako punkt wyjścia. Otworzy się w edytorze wizualnym, gdzie możesz zmienić w nim wszystko, a wcześniej wybrana część zostanie zastąpiona w tym slocie.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 sm:grid-cols-2">
                    {presets.map((p) => (
                        <div
                            key={p.key}
                            className="flex flex-col overflow-hidden rounded-xl border"
                        >
                            <PresetPreview preset={p} />
                            <div className="flex flex-1 flex-col gap-1 p-3">
                                <h3 className="text-sm font-semibold">
                                    {p.label}
                                </h3>
                                <p className="flex-1 text-xs text-muted-foreground">
                                    {p.description}
                                </p>
                                <Button
                                    size="sm"
                                    className="mt-2"
                                    disabled={busy !== null}
                                    onClick={() => use(p)}
                                >
                                    {busy === p.key && (
                                        <Loader2 className="animate-spin" />
                                    )}{' '}
                                    {t('Użyj tego projektu')}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
