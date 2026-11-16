import { ImagePlus, X } from 'lucide-react';
import { useState } from 'react';
import { MediaPicker } from '@/components/cms/media-picker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';
import type { MediaItem } from '@/types/cms';

type Props = {
    label: string;
    hint?: string;
    value: MediaItem | null;
    onChange: (item: MediaItem | null) => void;
    /** Klasy podglądu (rozmiar i tło), np. "h-16" dla logo. */
    previewClassName?: string;
    error?: string;
};

/** Pole wyboru obrazu z biblioteki mediów z podglądem i przyciskiem usuwania. */
export function ImageField({
    label,
    hint,
    value,
    onChange,
    previewClassName,
    error,
}: Props) {
    const [picking, setPicking] = useState(false);

    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {value && (
                <div
                    className={cn(
                        'relative w-fit max-w-xs overflow-hidden rounded-lg border bg-[repeating-conic-gradient(#f3f4f6_0%_25%,#fff_0%_50%)] bg-[length:16px_16px]',
                        !previewClassName && 'w-full',
                    )}
                >
                    <img
                        src={value.url}
                        alt={value.alt}
                        className={cn('block', previewClassName ?? 'w-full')}
                    />
                    <button
                        type="button"
                        aria-label={t('Usuń obraz')}
                        onClick={() => onChange(null)}
                        className="absolute top-1.5 right-1.5 rounded-full bg-black/70 p-1 text-white"
                    >
                        <X className="size-3.5" />
                    </button>
                </div>
            )}
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPicking(true)}
                >
                    <ImagePlus />{' '}
                    {value ? t('Zmień obraz') : t('Wybierz obraz')}
                </Button>
            </div>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-destructive">{error}</p>}
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                imagesOnly
                onPick={(items) => onChange(items[0] ?? null)}
            />
        </div>
    );
}
