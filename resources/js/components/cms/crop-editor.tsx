import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { MediaItem } from '@/types/cms';

type Rect = { x: number; y: number; width: number; height: number };

type Props = {
    media: MediaItem;
    size: { name: string; label: string; width: number; height: number };
    onSave: (rect: Rect) => void;
    onReset: () => void;
    onCancel: () => void;
    busy?: boolean;
};

/** Edytor kadru o stałych proporcjach rozmiaru. Współrzędne w pikselach oryginału. */
export function CropEditor({
    media,
    size,
    onSave,
    onReset,
    onCancel,
    busy,
}: Props) {
    const naturalW = media.original_width ?? 1;
    const naturalH = media.original_height ?? 1;
    const ratio = size.width / size.height;

    const initial = (): Rect => {
        const saved = media.crops[size.name];
        if (saved) return saved;
        const width = Math.min(naturalW, naturalH * ratio);
        const height = width / ratio;

        return {
            x: Math.round((naturalW - width) / 2),
            y: Math.round((naturalH - height) / 2),
            width: Math.round(width),
            height: Math.round(height),
        };
    };

    const [rect, setRect] = useState<Rect>(initial);
    const box = useRef<HTMLDivElement>(null);
    const drag = useRef<{
        mode: 'move' | 'resize';
        startX: number;
        startY: number;
        origin: Rect;
    } | null>(null);

    const scale = () => (box.current ? naturalW / box.current.clientWidth : 1);

    function onPointerDown(e: React.PointerEvent, mode: 'move' | 'resize') {
        e.preventDefault();
        e.stopPropagation();
        (e.target as HTMLElement).setPointerCapture(e.pointerId);
        drag.current = {
            mode,
            startX: e.clientX,
            startY: e.clientY,
            origin: rect,
        };
    }

    function onPointerMove(e: React.PointerEvent) {
        const d = drag.current;
        if (!d) return;
        const dx = (e.clientX - d.startX) * scale();
        const dy = (e.clientY - d.startY) * scale();

        if (d.mode === 'move') {
            setRect({
                ...d.origin,
                x: Math.round(
                    Math.max(
                        0,
                        Math.min(naturalW - d.origin.width, d.origin.x + dx),
                    ),
                ),
                y: Math.round(
                    Math.max(
                        0,
                        Math.min(naturalH - d.origin.height, d.origin.y + dy),
                    ),
                ),
            });

            return;
        }

        const maxW = Math.min(
            naturalW - d.origin.x,
            (naturalH - d.origin.y) * ratio,
        );
        const width = Math.round(
            Math.max(
                size.width / 4,
                Math.min(maxW, d.origin.width + Math.max(dx, dy * ratio)),
            ),
        );
        setRect({ ...d.origin, width, height: Math.round(width / ratio) });
    }

    const pct = (v: number, total: number) => `${(v / total) * 100}%`;

    return (
        <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
                {size.label} ({size.width}×{size.height}). Przeciągnij ramkę,
                aby wybrać kadr, a róg, aby zmienić jego wielkość.
            </p>
            <div
                ref={box}
                className="relative touch-none overflow-hidden rounded-lg bg-black select-none"
                onPointerMove={onPointerMove}
                onPointerUp={() => (drag.current = null)}
            >
                <img
                    src={media.original_url}
                    alt=""
                    draggable={false}
                    className="block w-full opacity-60"
                />
                <div
                    onPointerDown={(e) => onPointerDown(e, 'move')}
                    className="absolute cursor-move border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                    style={{
                        left: pct(rect.x, naturalW),
                        top: pct(rect.y, naturalH),
                        width: pct(rect.width, naturalW),
                        height: pct(rect.height, naturalH),
                    }}
                >
                    <div className="absolute inset-0 overflow-hidden">
                        <img
                            src={media.original_url}
                            alt=""
                            draggable={false}
                            className="max-w-none"
                            style={{
                                position: 'absolute',
                                width: `${(naturalW / rect.width) * 100}%`,
                                left: `${(-rect.x / rect.width) * 100}%`,
                                top: `${(-rect.y / rect.height) * 100}%`,
                            }}
                        />
                    </div>
                    <div
                        onPointerDown={(e) => onPointerDown(e, 'resize')}
                        className="absolute -right-2 -bottom-2 size-4 cursor-nwse-resize rounded-full border-2 border-white bg-brand"
                    />
                </div>
            </div>
            <div className="flex flex-wrap justify-between gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onReset}
                    disabled={busy || !media.crops[size.name]}
                >
                    Przywróć automatyczny kadr
                </Button>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onCancel}
                    >
                        Anuluj
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        disabled={busy}
                        onClick={() => onSave(rect)}
                    >
                        Zapisz kadr
                    </Button>
                </div>
            </div>
        </div>
    );
}
