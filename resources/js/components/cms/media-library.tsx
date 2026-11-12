import { FileText, Loader2, Search, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { MediaDetails } from '@/components/cms/media-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { api, ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types/cms';
import { toast } from 'sonner';

type Props = {
    /** manage: pełna edycja w panelu bocznym; select: wybór plików do pola. */
    mode: 'manage' | 'select';
    multiple?: boolean;
    imagesOnly?: boolean;
    selected?: MediaItem[];
    onSelectionChange?: (items: MediaItem[]) => void;
    className?: string;
};

type Page = {
    data: MediaItem[];
    meta: { current_page: number; last_page: number; total: number };
};

export function MediaLibrary({
    mode,
    multiple = false,
    imagesOnly = false,
    selected = [],
    onSelectionChange,
    className,
}: Props) {
    const [items, setItems] = useState<MediaItem[]>([]);
    const [meta, setMeta] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(0);
    const [active, setActive] = useState<MediaItem | null>(null);
    const [dragging, setDragging] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    const load = useCallback(
        async (page: number, replace: boolean) => {
            setLoading(true);
            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: '36',
                });
                if (search) params.set('search', search);
                if (imagesOnly) params.set('type', 'image');
                const res = await api<Page>(`/cms/media?${params}`);
                setItems((prev) =>
                    replace ? res.data : [...prev, ...res.data],
                );
                setMeta(res.meta);
            } catch (e) {
                toast.error((e as Error).message);
            } finally {
                setLoading(false);
            }
        },
        [search, imagesOnly],
    );

    useEffect(() => {
        const t = setTimeout(() => void load(1, true), search ? 250 : 0);

        return () => clearTimeout(t);
    }, [load, search]);

    async function upload(files: FileList | File[]) {
        for (const file of Array.from(files)) {
            setUploading((n) => n + 1);
            try {
                const body = new FormData();
                body.append('file', file);
                const created = await api<MediaItem>('/cms/media', {
                    method: 'POST',
                    body,
                });
                setItems((prev) => [created, ...prev]);
                setMeta((m) => ({ ...m, total: m.total + 1 }));
                if (mode === 'select') toggle(created);
            } catch (e) {
                toast.error(`${file.name}: ${(e as ApiError).message}`);
            } finally {
                setUploading((n) => n - 1);
            }
        }
    }

    function toggle(item: MediaItem) {
        if (mode === 'manage') {
            setActive(item);

            return;
        }

        const exists = selected.some((s) => s.id === item.id);
        onSelectionChange?.(
            multiple
                ? exists
                    ? selected.filter((s) => s.id !== item.id)
                    : [...selected, item]
                : exists
                  ? []
                  : [item],
        );
    }

    const replaceItem = (updated: MediaItem) => {
        setItems((prev) =>
            prev.map((m) => (m.id === updated.id ? updated : m)),
        );
        setActive(updated);
    };

    return (
        <div className={cn('flex min-h-0 gap-4', className)}>
            <div
                className={cn(
                    'flex min-w-0 flex-1 flex-col gap-3',
                    dragging && 'rounded-lg ring-2 ring-brand',
                )}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    if (e.dataTransfer.files.length)
                        void upload(e.dataTransfer.files);
                }}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Szukaj plików…"
                            className="pl-9"
                        />
                    </div>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        hidden
                        accept={imagesOnly ? 'image/*' : undefined}
                        onChange={(e) =>
                            e.target.files && void upload(e.target.files)
                        }
                    />
                    <Button
                        type="button"
                        onClick={() => input.current?.click()}
                    >
                        {uploading > 0 ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Upload />
                        )}{' '}
                        Wgraj pliki
                    </Button>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    {items.length === 0 && !loading ? (
                        <p className="py-16 text-center text-sm text-muted-foreground">
                            Brak plików. Przeciągnij je tutaj albo użyj
                            przycisku „Wgraj pliki”.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            {items.map((item) => {
                                const isSelected =
                                    mode === 'select'
                                        ? selected.some((s) => s.id === item.id)
                                        : active?.id === item.id;

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        onClick={() => toggle(item)}
                                        className={cn(
                                            'group relative aspect-square overflow-hidden rounded-lg border bg-muted text-left',
                                            isSelected &&
                                                'ring-2 ring-brand ring-offset-2 ring-offset-background',
                                        )}
                                    >
                                        {item.mime.startsWith('image/') ? (
                                            <img
                                                src={
                                                    item.sizes.thumbnail?.url ??
                                                    item.url
                                                }
                                                alt={item.alt}
                                                loading="lazy"
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <span className="flex size-full flex-col items-center justify-center gap-1 p-2 text-muted-foreground">
                                                <FileText className="size-8" />
                                                <span className="line-clamp-2 text-center text-xs break-all">
                                                    {item.filename}
                                                </span>
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                    {loading && (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            Ładowanie…
                        </p>
                    )}
                    {!loading && meta.current_page < meta.last_page && (
                        <div className="py-4 text-center">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    void load(meta.current_page + 1, false)
                                }
                            >
                                Pokaż więcej
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {mode === 'manage' && active && (
                <MediaDetails
                    key={active.id}
                    media={active}
                    onChange={replaceItem}
                    onDeleted={() => {
                        setItems((prev) =>
                            prev.filter((m) => m.id !== active.id),
                        );
                        setActive(null);
                    }}
                />
            )}
        </div>
    );
}
