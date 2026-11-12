import { Head, Link } from '@inertiajs/react';
import grapesjs from 'grapesjs';
import type { Block, Editor, ProjectData } from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import '../../../css/builder.css';
import {
    ArrowLeft,
    Eye,
    ImagePlus,
    Layers,
    LayoutGrid,
    Loader2,
    Monitor,
    PanelLeft,
    PanelRight,
    Redo2,
    Save,
    SlidersHorizontal,
    Smartphone,
    Tablet,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { MediaPicker } from '@/components/cms/media-picker';
import { StatusBadge } from '@/components/cms/status-badge';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { registerBlocks } from '@/lib/builder/blocks';
import { registerComponentTypes } from '@/lib/builder/types';
import { cmsUrl } from '@/lib/cms-urls';
import { cn } from '@/lib/utils';
import type { ContentStatus, ContentTypeInfo, MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

type Props = {
    type: ContentTypeInfo;
    content: { id: number; title: string; status: ContentStatus; slug: string };
    project: ProjectData | null;
};

const devices = [
    { id: 'Desktop', label: t('Komputer'), icon: Monitor, width: '' },
    { id: 'Tablet', label: t('Tablet'), icon: Tablet, width: '768px' },
    { id: 'Mobile', label: t('Telefon'), icon: Smartphone, width: '375px' },
];

const panelClass =
    'pointer-events-auto flex min-h-0 flex-col overflow-hidden rounded-2xl border bg-white/95 shadow-xl backdrop-blur';

export default function Builder({ type, content, project }: Props) {
    const canvas = useRef<HTMLDivElement>(null);
    const blocksEl = useRef<HTMLDivElement>(null);
    const layersEl = useRef<HTMLDivElement>(null);
    const traitsEl = useRef<HTMLDivElement>(null);
    const editorRef = useRef<Editor | null>(null);

    const [device, setDevice] = useState('Desktop');
    const [leftOpen, setLeftOpen] = useState(true);
    const [rightOpen, setRightOpen] = useState(true);
    const [tab, setTab] = useState<'props' | 'layers'>('props');
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [empty, setEmpty] = useState(!project);
    const [status, setStatus] = useState<ContentStatus>(content.status);
    const [selected, setSelected] = useState<{
        name: string;
        isImage: boolean;
    } | null>(null);
    const [history, setHistory] = useState({ undo: false, redo: false });
    const [picking, setPicking] = useState(false);

    const save = useCallback(
        async (publish = false) => {
            const editor = editorRef.current;
            if (!editor) return;

            setSaving(true);
            try {
                const result = await api<{ status: ContentStatus }>(
                    `/cms/content/${type.key}/${content.id}/builder`,
                    {
                        method: 'PUT',
                        body: {
                            project: editor.getProjectData(),
                            ...(publish ? { status: 'published' } : {}),
                        },
                    },
                );
                setStatus(result.status);
                setDirty(false);
                editor.clearDirtyCount?.();
                toast.success(publish ? t('Opublikowano.') : t('Zapisano.'));
            } catch (e) {
                toast.error((e as Error).message);
            } finally {
                setSaving(false);
            }
        },
        [type.key, content.id],
    );

    // Stały uchwyt do najnowszego save, używany przez skrót klawiszowy wewnątrz edytora.
    const saveRef = useRef(save);
    useEffect(() => {
        saveRef.current = save;
    }, [save]);

    useEffect(() => {
        if (
            !canvas.current ||
            !blocksEl.current ||
            !layersEl.current ||
            !traitsEl.current
        )
            return;

        const editor = grapesjs.init({
            container: canvas.current,
            height: '100%',
            width: 'auto',
            fromElement: false,
            storageManager: false,
            panels: { defaults: [] },
            blockManager: { appendTo: blocksEl.current },
            layerManager: { appendTo: layersEl.current },
            traitManager: { appendTo: traitsEl.current },
            styleManager: { sectors: [] },
            selectorManager: { componentFirst: true },
            canvas: { styles: ['/css/lumin-blocks.css'] },
            deviceManager: {
                devices: devices.map((d) => ({
                    id: d.id,
                    name: d.label,
                    width: d.width || null,
                })),
            },
        });

        registerComponentTypes(editor);
        registerBlocks(editor);

        if (project) editor.loadProjectData(project);

        const refresh = () => {
            setEmpty(editor.getComponents().length === 0);
            setHistory({
                undo: editor.UndoManager.hasUndo(),
                redo: editor.UndoManager.hasRedo(),
            });
        };

        editor.on('update', () => {
            setDirty(true);
            refresh();
        });
        editor.on('load', refresh);
        editor.on('component:selected', (c) =>
            setSelected({ name: c.getName(), isImage: c.is('lm-image') }),
        );
        editor.on('component:deselected', () => setSelected(null));
        editor.Keymaps.add(
            'lumin-save',
            '⌘+s, ctrl+s',
            () => void saveRef.current(false),
        );

        // Kliknięcie bloku dodaje go na koniec strony (alternatywa dla przeciągania, np. na ekranach dotykowych).
        // Nasłuch w fazie przechwytywania, bo GrapesJS obsługuje własne zdarzenia bloków.
        const blocksContainer = blocksEl.current;
        const addBlock = (e: MouseEvent) => {
            const label = (e.target as HTMLElement)
                .closest<HTMLElement>('.gjs-block')
                ?.getAttribute('title');
            const block = editor.Blocks.getAll().models.find(
                (b: Block) => b.getLabel() === label,
            );
            if (!block) return;

            const added =
                editor.getWrapper()?.append(block.getContent() as string) ?? [];
            if (added[0]) {
                editor.select(added[0]);
                editor.Canvas.scrollTo(added[0], { behavior: 'smooth' });
            }
        };
        blocksContainer.addEventListener('click', addBlock, true);

        editorRef.current = editor;
        refresh();
        setDirty(false);

        return () => {
            blocksContainer.removeEventListener('click', addBlock, true);
            editor.destroy();
            editorRef.current = null;
        };
        // Edytor tworzymy raz dla danej treści.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [content.id]);

    useEffect(() => {
        const warn = (e: BeforeUnloadEvent) => {
            if (dirty) e.preventDefault();
        };
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const changeDevice = (id: string) => {
        setDevice(id);
        editorRef.current?.setDevice(id);
    };

    const pickImage = (items: MediaItem[]) => {
        const editor = editorRef.current;
        const component = editor?.getSelected();
        const media = items[0];
        if (!component || !media) return;

        component.set('src', media.url);
        component.addAttributes({
            src: media.url,
            'data-media-id': String(media.id),
            ...(media.alt ? { alt: media.alt } : {}),
        });
        setDirty(true);
    };

    const preview = async () => {
        if (dirty) await save(false);
        window.open(
            `/cms/content/${type.key}/${content.id}/preview`,
            '_blank',
            'noopener',
        );
    };

    return (
        <>
            <Head title={t('Edytor: {name}', { name: content.title })} />
            <div className="lumin-builder fixed inset-0 overflow-hidden bg-neutral-200">
                <div ref={canvas} className="absolute inset-0" />

                {empty && (
                    <div className="pointer-events-none absolute inset-0 z-[55] flex items-center justify-center">
                        <div className="rounded-2xl bg-white/90 px-8 py-6 text-center shadow-lg">
                            <LayoutGrid className="mx-auto mb-2 size-8 text-brand" />
                            <p className="font-medium">
                                {t('Zacznij od pustej strony')}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Przeciągnij blok z panelu po lewej na stronę albo kliknij go, aby dodać na końcu.',
                                )}
                            </p>
                        </div>
                    </div>
                )}

                <div className="pointer-events-none absolute inset-0 z-[60] flex flex-col gap-3 p-3">
                    {/* Pasek górny */}
                    <div className="flex items-start justify-between gap-3">
                        <div
                            className={cn(
                                panelClass,
                                'flex-row items-center gap-1 p-1.5',
                            )}
                        >
                            <Button
                                asChild
                                variant="ghost"
                                size="icon"
                                className="size-9"
                            >
                                <Link
                                    href={cmsUrl.contentEdit(
                                        type.key,
                                        content.id,
                                    )}
                                    aria-label={t('Wróć do treści')}
                                >
                                    <ArrowLeft />
                                </Link>
                            </Button>
                            <div className="flex min-w-0 items-center gap-2 px-1">
                                <span className="max-w-48 truncate text-sm font-medium">
                                    {content.title}
                                </span>
                                <StatusBadge status={status} />
                                {dirty && (
                                    <span className="text-xs text-amber-600">
                                        {t('niezapisane')}
                                    </span>
                                )}
                            </div>
                        </div>

                        <div
                            className={cn(
                                panelClass,
                                'flex-row items-center gap-0.5 p-1.5',
                            )}
                        >
                            {devices.map(({ id, label, icon: Icon }) => (
                                <Button
                                    key={id}
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={label}
                                    title={label}
                                    onClick={() => changeDevice(id)}
                                    className={cn(
                                        'size-9',
                                        device === id &&
                                            'bg-brand-soft text-brand-foreground',
                                    )}
                                >
                                    <Icon />
                                </Button>
                            ))}
                            <span className="mx-1 h-5 w-px bg-border" />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Cofnij')}
                                title={t('Cofnij')}
                                className="size-9"
                                disabled={!history.undo}
                                onClick={() =>
                                    editorRef.current?.UndoManager.undo()
                                }
                            >
                                <Undo2 />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Ponów')}
                                title={t('Ponów')}
                                className="size-9"
                                disabled={!history.redo}
                                onClick={() =>
                                    editorRef.current?.UndoManager.redo()
                                }
                            >
                                <Redo2 />
                            </Button>
                        </div>

                        <div
                            className={cn(
                                panelClass,
                                'flex-row items-center gap-1.5 p-1.5',
                            )}
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => void preview()}
                            >
                                <Eye /> {t('Podgląd')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={saving}
                                onClick={() => void save(false)}
                            >
                                {saving ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Save />
                                )}{' '}
                                {t('Zapisz')}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={saving}
                                onClick={() => void save(true)}
                            >
                                {status === 'published'
                                    ? t('Zaktualizuj')
                                    : t('Opublikuj')}
                            </Button>
                        </div>
                    </div>

                    {/* Panele boczne */}
                    <div className="flex min-h-0 flex-1 items-stretch justify-between gap-3">
                        <div className="flex min-h-0 flex-col gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={t('Bloki')}
                                title={t('Bloki')}
                                className="pointer-events-auto size-9 bg-white"
                                onClick={() => setLeftOpen((o) => !o)}
                            >
                                <PanelLeft />
                            </Button>
                            <div
                                className={cn(
                                    panelClass,
                                    'min-h-0 w-60 flex-1',
                                    !leftOpen && 'hidden',
                                )}
                            >
                                <p className="border-b px-4 py-3 text-sm font-semibold">
                                    {t('Bloki')}
                                </p>
                                <div
                                    ref={blocksEl}
                                    className="min-h-0 flex-1 overflow-y-auto"
                                />
                            </div>
                        </div>

                        <div className="flex min-h-0 flex-col items-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={t('Właściwości')}
                                title={t('Właściwości')}
                                className="pointer-events-auto size-9 bg-white"
                                onClick={() => setRightOpen((o) => !o)}
                            >
                                <PanelRight />
                            </Button>
                            <div
                                className={cn(
                                    panelClass,
                                    'min-h-0 w-72 flex-1',
                                    !rightOpen && 'hidden',
                                )}
                            >
                                <div className="flex border-b p-1.5">
                                    {[
                                        {
                                            id: 'props',
                                            label: t('Właściwości'),
                                            icon: SlidersHorizontal,
                                        },
                                        {
                                            id: 'layers',
                                            label: t('Warstwy'),
                                            icon: Layers,
                                        },
                                    ].map(({ id, label, icon: Icon }) => (
                                        <button
                                            key={id}
                                            type="button"
                                            onClick={() =>
                                                setTab(id as 'props' | 'layers')
                                            }
                                            className={cn(
                                                'flex flex-1 items-center justify-center gap-1.5 rounded-lg py-1.5 text-sm',
                                                tab === id
                                                    ? 'bg-brand-soft font-medium text-brand-foreground'
                                                    : 'text-muted-foreground hover:bg-muted',
                                            )}
                                        >
                                            <Icon className="size-4" /> {label}
                                        </button>
                                    ))}
                                </div>
                                <div
                                    className={cn(
                                        'min-h-0 flex-1 overflow-y-auto p-3',
                                        tab !== 'props' && 'hidden',
                                    )}
                                >
                                    {!selected && (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            {t(
                                                'Kliknij element na stronie, aby zmienić jego wygląd. Dwukrotne kliknięcie tekstu włącza edycję.',
                                            )}
                                        </p>
                                    )}
                                    {selected && (
                                        <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            {selected.name}
                                        </p>
                                    )}
                                    {selected?.isImage && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mb-2 w-full"
                                            onClick={() => setPicking(true)}
                                        >
                                            <ImagePlus />{' '}
                                            {t('Wybierz obraz z biblioteki')}
                                        </Button>
                                    )}
                                    <div ref={traitsEl} />
                                </div>
                                <div
                                    ref={layersEl}
                                    className={cn(
                                        'min-h-0 flex-1 overflow-y-auto',
                                        tab !== 'layers' && 'hidden',
                                    )}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <MediaPicker
                    open={picking}
                    onOpenChange={setPicking}
                    imagesOnly
                    onPick={pickImage}
                />
            </div>
        </>
    );
}
