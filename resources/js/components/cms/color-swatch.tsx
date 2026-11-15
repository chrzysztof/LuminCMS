import { useEffect, useRef, useState } from 'react';
import { HexColorInput, HexColorPicker } from 'react-colorful';
import { cn } from '@/lib/utils';

const HEX = /^#[0-9a-f]{6}$/i;

/** Próbka koloru z oknem wyboru: paleta plus pole kodu szesnastkowego (#RRGGBB). */
export function ColorSwatch({
    id,
    value,
    onChange,
    className,
    label,
}: {
    id?: string;
    value: string;
    onChange: (hex: string) => void;
    className?: string;
    label?: string;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const color = HEX.test(value) ? value : '#000000';

    useEffect(() => {
        if (!open) return;

        const close = (e: MouseEvent | KeyboardEvent) => {
            if (e instanceof KeyboardEvent) {
                if (e.key === 'Escape') setOpen(false);

                return;
            }

            if (ref.current && !ref.current.contains(e.target as Node))
                setOpen(false);
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative shrink-0">
            <button
                id={id}
                type="button"
                aria-label={label}
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className={cn('size-9 rounded-md border p-1', className)}
            >
                <span
                    className="block size-full rounded-[4px] border border-black/10"
                    style={{ backgroundColor: color }}
                />
            </button>
            {open && (
                <div className="absolute top-11 left-0 z-50 w-56 space-y-3 rounded-xl border bg-popover p-3 shadow-lg">
                    <HexColorPicker
                        color={color}
                        onChange={onChange}
                        style={{ width: '100%' }}
                    />
                    <div className="flex items-center gap-2">
                        <span
                            className="size-8 shrink-0 rounded-md border border-black/10"
                            style={{ backgroundColor: color }}
                        />
                        <HexColorInput
                            color={color}
                            onChange={onChange}
                            prefixed
                            aria-label="HEX"
                            className="h-8 w-full rounded-md border bg-background px-2 font-mono text-sm uppercase"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
