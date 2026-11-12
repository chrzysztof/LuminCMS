import { Crop, Loader2, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CropEditor } from '@/components/cms/crop-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api } from '@/lib/api';
import { formatBytes } from '@/lib/format';
import type { MediaItem } from '@/types/cms';

type MediaSizeRow = {
    id: number;
    name: string;
    label: string;
    width: number;
    height: number;
    crop: boolean;
    active: boolean;
};

type Props = {
    media: MediaItem;
    onChange: (media: MediaItem) => void;
    onDeleted: () => void;
};

export function MediaDetails({ media, onChange, onDeleted }: Props) {
    const [alt, setAlt] = useState(media.alt);
    const [title, setTitle] = useState(media.title);
    const [caption, setCaption] = useState(media.caption ?? '');
    const [saving, setSaving] = useState(false);
    const [sizes, setSizes] = useState<MediaSizeRow[]>([]);
    const [cropping, setCropping] = useState<MediaSizeRow | null>(null);

    useEffect(() => {
        api<{ data: MediaSizeRow[] }>('/cms/media-sizes')
            .then((r) => setSizes(r.data.filter((s) => s.crop && s.active)))
            .catch(() => {});
    }, []);

    const isImage = media.mime.startsWith('image/');
    const dirty =
        alt !== media.alt ||
        title !== media.title ||
        caption !== (media.caption ?? '');

    async function run<T>(fn: () => Promise<T>) {
        setSaving(true);
        try {
            return await fn();
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            setSaving(false);
        }
    }

    const save = () =>
        run(async () => {
            onChange(
                await api<MediaItem>(`/cms/media/${media.id}`, {
                    method: 'PATCH',
                    body: { alt, title, caption },
                }),
            );
            toast.success('Zapisano.');
        });

    const setFocal = (e: React.MouseEvent<HTMLDivElement>) => {
        const r = e.currentTarget.getBoundingClientRect();
        const focal_x =
            Math.round(((e.clientX - r.left) / r.width) * 100) / 100;
        const focal_y =
            Math.round(((e.clientY - r.top) / r.height) * 100) / 100;
        void run(async () =>
            onChange(
                await api<MediaItem>(`/cms/media/${media.id}`, {
                    method: 'PATCH',
                    body: { focal_x, focal_y },
                }),
            ),
        );
    };

    const remove = () => {
        if (
            !confirm(
                'Usunąć ten plik na stałe? Pola, które go używają, utracą obraz.',
            )
        )
            return;
        void run(async () => {
            await api(`/cms/media/${media.id}`, { method: 'DELETE' });
            onDeleted();
        });
    };

    return (
        <aside className="hidden w-80 shrink-0 space-y-4 overflow-y-auto rounded-xl border bg-card p-4 lg:block">
            {isImage ? (
                <div className="space-y-1">
                    <div
                        className="relative cursor-crosshair overflow-hidden rounded-lg bg-muted"
                        onClick={setFocal}
                        title="Kliknij, aby ustawić punkt ogniskowy"
                    >
                        <img
                            src={media.url}
                            alt={media.alt}
                            className="block w-full"
                        />
                        <span
                            className="pointer-events-none absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-brand shadow"
                            style={{
                                left: `${media.focal_x * 100}%`,
                                top: `${media.focal_y * 100}%`,
                            }}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Kliknij obraz, aby ustawić punkt ogniskowy (środek
                        kadrowania).
                    </p>
                </div>
            ) : (
                <p className="rounded-lg bg-muted p-4 text-center text-sm break-all">
                    {media.filename}
                </p>
            )}

            <dl className="grid grid-cols-2 gap-x-3 text-xs text-muted-foreground">
                <dt>Plik</dt>
                <dd className="truncate text-foreground">{media.filename}</dd>
                <dt>Rozmiar</dt>
                <dd className="text-foreground">{formatBytes(media.size)}</dd>
                {media.original_width && (
                    <>
                        <dt>Wymiary</dt>
                        <dd className="text-foreground">
                            {media.original_width}×{media.original_height}
                        </dd>
                    </>
                )}
            </dl>

            <div className="space-y-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="m-alt">Tekst alternatywny (ALT)</Label>
                    <Input
                        id="m-alt"
                        value={alt}
                        onChange={(e) => setAlt(e.target.value)}
                        placeholder="Opisz, co jest na obrazie"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="m-title">Tytuł</Label>
                    <Input
                        id="m-title"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="m-caption">Podpis</Label>
                    <Input
                        id="m-caption"
                        value={caption}
                        onChange={(e) => setCaption(e.target.value)}
                    />
                </div>
                <Button
                    type="button"
                    size="sm"
                    disabled={!dirty || saving}
                    onClick={() => void save()}
                >
                    {saving && <Loader2 className="animate-spin" />} Zapisz
                </Button>
            </div>

            {isImage && sizes.length > 0 && (
                <div className="space-y-2">
                    <p className="text-sm font-medium">Kadrowanie</p>
                    {sizes.map((s) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => setCropping(s)}
                            className="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm hover:bg-muted"
                        >
                            <span>
                                {s.label}{' '}
                                <span className="text-muted-foreground">
                                    {s.width}×{s.height}
                                </span>
                            </span>
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                {media.crops[s.name] ? 'własny' : 'auto'}{' '}
                                <Crop className="size-3.5" />
                            </span>
                        </button>
                    ))}
                </div>
            )}

            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="text-destructive"
                onClick={remove}
                disabled={saving}
            >
                <Trash2 /> Usuń plik
            </Button>

            <Dialog
                open={cropping !== null}
                onOpenChange={(o) => !o && setCropping(null)}
            >
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Kadrowanie obrazu</DialogTitle>
                    </DialogHeader>
                    {cropping && (
                        <CropEditor
                            media={media}
                            size={cropping}
                            busy={saving}
                            onCancel={() => setCropping(null)}
                            onSave={(rect) =>
                                void run(async () => {
                                    onChange(
                                        await api<MediaItem>(
                                            `/cms/media/${media.id}/crops/${cropping.name}`,
                                            { method: 'PUT', body: rect },
                                        ),
                                    );
                                    setCropping(null);
                                })
                            }
                            onReset={() =>
                                void run(async () => {
                                    onChange(
                                        await api<MediaItem>(
                                            `/cms/media/${media.id}/crops/${cropping.name}`,
                                            { method: 'DELETE' },
                                        ),
                                    );
                                    setCropping(null);
                                })
                            }
                        />
                    )}
                </DialogContent>
            </Dialog>
        </aside>
    );
}
