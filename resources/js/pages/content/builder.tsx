import { Head, Link } from '@inertiajs/react';
import grapesjs from 'grapesjs';
import type { Block, Component, Editor, ProjectData } from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import '../../../css/builder.css';
import {
    ArrowLeft,
    Bookmark,
    BookmarkPlus,
    Eye,
    History,
    LayoutTemplate,
    ImagePlus,
    Layers,
    LayoutGrid,
    Loader2,
    Monitor,
    PanelLeft,
    Redo2,
    Save,
    Search,
    Settings2,
    SlidersHorizontal,
    Smartphone,
    Tablet,
    Undo2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    HistoryDialog,
    formatRevisionDate,
} from '@/components/cms/builder/history-dialog';
import {
    FieldBinding,
    type CustomField,
} from '@/components/cms/builder/field-binding';
import { LocalStylesPanel } from '@/components/cms/builder/local-styles-panel';
import { RichTextPanel } from '@/components/cms/builder/richtext-panel';
import { DisplayConditions } from '@/components/cms/builder/display-conditions';
import { BuilderContextMenu } from '@/components/cms/builder/context-menu';
import { PageTemplatesDialog } from '@/components/cms/builder/page-templates-dialog';
import {
    ManageBlocksDialog,
    SaveBlockDialog,
} from '@/components/cms/builder/saved-blocks-dialogs';
import { StylePanel, type Device } from '@/components/cms/builder/style-panel';
import { MediaPicker } from '@/components/cms/media-picker';
import { ThemeBlockDialog } from '@/components/cms/theme-block-dialog';
import { StatusBadge } from '@/components/cms/status-badge';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { registerLayerDelete, registerShortcuts } from '@/lib/builder/actions';
import { registerBlocks } from '@/lib/builder/blocks';
import { filterBlocks } from '@/lib/builder/block-search';
import {
    registerThemeBlocks,
    type ThemeBlock,
} from '@/lib/builder/theme-blocks';
import { syncSavedBlocks, type SavedBlock } from '@/lib/builder/saved-blocks';
import { registerComponentTypes } from '@/lib/builder/types';
import { cmsUrl } from '@/lib/cms-urls';
import { cn } from '@/lib/utils';
import type { ContentStatus, ContentTypeInfo, MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

type Props = {
    type: ContentTypeInfo;
    content: { id: number; title: string; status: ContentStatus; slug: string };
    project: ProjectData | null;
    themeBlocks: ThemeBlock[];
    customFields: CustomField[];
    savedBlocks: SavedBlock[];
    /** Autozapis nowszy niż ostatni zapis: niezapisane zmiany do odzyskania. */
    recovery: { id: number; created_at: string } | null;
    /** Gotowy projekt części (HTML), wczytywany do pustej części przy pierwszym otwarciu. */
    seedHtml: string | null;
    /** Arkusze motywu dodawane do kanwy. */
    themeStyles: string[];
};

/** Co ile ms edytor odkłada autozapis, jeśli są niezapisane zmiany. */
const AUTOSAVE_MS = 30_000;

const devices = [
    { id: 'Desktop', label: t('Komputer'), icon: Monitor, width: '' },
    {
        id: 'Tablet',
        label: t('Tablet'),
        icon: Tablet,
        width: '768px',
        media: '900px',
    },
    {
        id: 'Mobile',
        label: t('Telefon'),
        icon: Smartphone,
        width: '375px',
        media: '640px',
    },
];

/** W edytorze elementy ukryte na danym urządzeniu zostają widoczne (przygaszone), żeby dało się je zaznaczyć. */
const HIDDEN_PREVIEW_CSS = [
    '@media (min-width:901px){body .lm-hide-desktop',
    '@media (min-width:641px) and (max-width:900px){body .lm-hide-tablet',
    '@media (max-width:640px){body .lm-hide-mobile',
]
    .map(
        (selector) =>
            `${selector}{display:revert!important;opacity:.35;outline:3px dashed #94a3b8}}`,
    )
    .join('');

const panelClass =
    'pointer-events-auto flex min-h-0 flex-col overflow-hidden rounded-2xl border bg-white/95 shadow-xl backdrop-blur';

export default function Builder({
    type,
    content,
    project,
    themeBlocks,
    customFields,
    savedBlocks,
    recovery,
    seedHtml,
    themeStyles,
}: Props) {
    const canvas = useRef<HTMLDivElement>(null);
    const blocksEl = useRef<HTMLDivElement>(null);
    const layersEl = useRef<HTMLDivElement>(null);
    const traitsEl = useRef<HTMLDivElement>(null);
    const editorRef = useRef<Editor | null>(null);
    const [editorInst, setEditorInst] = useState<Editor | null>(null);

    const [device, setDevice] = useState('Desktop');
    const [panelOpen, setPanelOpen] = useState(true);
    const [tab, setTab] = useState<'blocks' | 'props' | 'layers'>('blocks');
    const [blockQuery, setBlockQuery] = useState('');
    const tabRef = useRef(tab);

    // Filtr listy bloków; ponawiany, gdy GrapesJS przerysuje listę (np. po zmianie "Moich bloków").
    useEffect(() => {
        const el = blocksEl.current;
        if (!el) return;
        const apply = () => filterBlocks(el, blockQuery);
        apply();
        const observer = new MutationObserver(apply);
        observer.observe(el, { childList: true, subtree: true });

        return () => observer.disconnect();
    }, [blockQuery, editorInst]);
    // Wstawienie bloku też zaznacza element - wtedy panel zostaje na liście bloków.
    const adding = useRef(false);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [empty, setEmpty] = useState(!project);
    const [status, setStatus] = useState<ContentStatus>(content.status);
    const [menu, setMenu] = useState<{
        x: number;
        y: number;
        component: Component;
    } | null>(null);
    const [selected, setSelected] = useState<{
        name: string;
        isImage: boolean;
        isBlock: boolean;
        isRichText: boolean;
        isWrapper: boolean;
    } | null>(null);
    const [blockDialog, setBlockDialog] = useState<{
        block: ThemeBlock;
        values: Record<string, unknown>;
    } | null>(null);
    const [history, setHistory] = useState({ undo: false, redo: false });
    const [picking, setPicking] = useState(false);
    const [templatesOpen, setTemplatesOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [saved, setSaved] = useState<SavedBlock[]>(savedBlocks);
    const [saveBlockOpen, setSaveBlockOpen] = useState(false);
    const [manageOpen, setManageOpen] = useState(false);
    const [savingBlock, setSavingBlock] = useState(false);
    const [autosavedAt, setAutosavedAt] = useState<string | null>(null);
    const apiBase = `/cms/content/${type.key}/${content.id}/builder`;

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
        tabRef.current = tab;
    }, [tab]);

    const dirtyRef = useRef(false);
    useEffect(() => {
        dirtyRef.current = dirty;
    }, [dirty]);

    // Autozapis: co jakiś czas odkłada bieżący stan do historii (strona na żywo się nie zmienia).
    useEffect(() => {
        let last = '';
        const timer = window.setInterval(() => {
            const editor = editorRef.current;
            if (!editor || !dirtyRef.current) return;

            const project = editor.getProjectData();
            const json = JSON.stringify(project);
            if (json === last) return;
            last = json;

            api<{ saved_at: string }>(`${apiBase}/autosave`, {
                method: 'PUT',
                body: { project },
            })
                .then((r) => setAutosavedAt(r.saved_at))
                .catch(() => {
                    last = '';
                });
        }, AUTOSAVE_MS);

        return () => window.clearInterval(timer);
    }, [apiBase]);

    const restoreRevision = useCallback(
        async (id: number) => {
            const editor = editorRef.current;
            if (!editor) return;

            try {
                // Najpierw odkładamy obecny stan, żeby przywrócenie niczego nie zgubiło.
                if (dirtyRef.current)
                    await api(`${apiBase}/autosave`, {
                        method: 'PUT',
                        body: { project: editor.getProjectData() },
                    });

                const { project: restored } = await api<{
                    project: ProjectData;
                }>(`${apiBase}/revisions/${id}`);
                editor.loadProjectData(restored);
                setDirty(true);
                toast.success(
                    t(
                        'Przywrócono wersję. Zapisz, aby zastąpiła obecną stronę.',
                    ),
                );
            } catch (e) {
                toast.error((e as Error).message);
                throw e;
            }
        },
        [apiBase],
    );

    // Po wejściu do edytora proponujemy odzyskanie niezapisanych zmian z autozapisu.
    useEffect(() => {
        if (!recovery) return;

        const id = toast(t('Znaleziono niezapisane zmiany z autozapisu'), {
            description: formatRevisionDate(recovery.created_at),
            duration: Infinity,
            action: {
                label: t('Przywróć'),
                onClick: () => void restoreRevision(recovery.id),
            },
        });

        return () => {
            toast.dismiss(id);
        };
    }, [recovery, restoreRevision]);

    useEffect(() => {
        if (editorInst) syncSavedBlocks(editorInst, saved);
    }, [editorInst, saved]);

    const saveAsBlock = async (name: string) => {
        const component = editorRef.current?.getSelected();
        if (!component) return;

        setSavingBlock(true);
        try {
            const block = await api<SavedBlock>('/cms/saved-blocks', {
                method: 'POST',
                body: { name, html: component.toHTML() },
            });
            setSaved((list) =>
                [...list, block].sort((a, b) => a.name.localeCompare(b.name)),
            );
            setSaveBlockOpen(false);
            toast.success(t('Blok zapisany w „Moje bloki”.'));
        } catch (e) {
            toast.error((e as Error).message);
        } finally {
            setSavingBlock(false);
        }
    };

    const deleteBlock = async (block: SavedBlock) => {
        try {
            await api(`/cms/saved-blocks/${block.id}`, { method: 'DELETE' });
            setSaved((list) => list.filter((b) => b.id !== block.id));
        } catch (e) {
            toast.error((e as Error).message);
        }
    };

    const openBlockSettings = useCallback(
        (component: Component) => {
            const block = themeBlocks.find(
                (b) => b.name === component.get('block'),
            );
            if (block)
                setBlockDialog({
                    block,
                    values: (component.get('attrs') ?? {}) as Record<
                        string,
                        unknown
                    >,
                });
        },
        [themeBlocks],
    );

    const applyBlockSettings = (values: Record<string, unknown>) => {
        const component = editorRef.current?.getSelected();
        if (component?.is('lm-block')) {
            component.set('attrs', { ...values });
            setDirty(true);
        }
        setBlockDialog(null);
    };

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
            protectedCss: `body{margin:0}${HIDDEN_PREVIEW_CSS}[data-lm-field]{outline:3px dashed #7c3aed;outline-offset:2px}[data-lm-show]{outline:3px dashed #d97706;outline-offset:2px}.gjs-dashed *[data-gjs-highlightable]{outline-width:3px!important}.gjs-selected,.gjs-selected-parent{outline-width:3px!important}`,
            canvas: {
                styles: [
                    '/css/lumin-blocks.css',
                    '/theme-vars.css',
                    '/theme-blocks.css',
                    ...themeStyles,
                ],
            },
            deviceManager: {
                devices: devices.map((d) => ({
                    id: d.id,
                    name: d.label,
                    width: d.width || null,
                    widthMedia: d.media || null,
                })),
            },
        });

        registerComponentTypes(editor);
        registerBlocks(editor);
        registerThemeBlocks(editor, themeBlocks);

        if (project) {
            editor.loadProjectData(project);
        } else if (seedHtml) {
            editor.setComponents(seedHtml);
            // Gotowy projekt zapisujemy od razu, żeby publiczna witryna zaczęła go używać.
            window.setTimeout(() => void saveRef.current(false), 600);
        }

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
        editor.on('block:drag:start', () => {
            adding.current = true;
        });
        editor.on('block:drag:stop', () => {
            window.setTimeout(() => (adding.current = false), 500);
        });
        editor.on('component:selected', (c) => {
            // Zaznaczenie elementu na stronie przełącza panel na jego właściwości.
            if (!adding.current && tabRef.current === 'blocks') setTab('props');
            setSelected({
                name: c.getName(),
                isImage: c.is('lm-image'),
                isBlock: c.is('lm-block'),
                isRichText: c.is('lm-richtext'),
                isWrapper: c.is('wrapper'),
            });
        });
        editor.on('component:deselected', () => setSelected(null));
        // Po upuszczeniu bloku motywu od razu pokazujemy jego ustawienia.
        editor.on('block:drag:stop', (c) => {
            if (c?.is('lm-block')) openBlockSettings(c);
            // Akapit edytuje się we właściwościach, więc od razu je pokazujemy.
            if (c?.is('lm-richtext')) setTab('props');
        });
        editor.Keymaps.add(
            'lumin-save',
            '⌘+s, ctrl+s',
            () => void saveRef.current(false),
        );

        registerShortcuts(editor);
        registerLayerDelete(editor, t('Usuń element'));

        // Menu prawego przycisku: zaznacza element pod kursorem i otwiera własne menu zamiast przeglądarkowego.
        let frameDoc: Document | null = null;
        const onContext = (e: MouseEvent) => {
            const el = e.target as
                | (HTMLElement & { __gjsv?: { model: Component } })
                | null;
            const comp = el?.closest<
                HTMLElement & { __gjsv?: { model: Component } }
            >('*')?.__gjsv?.model;
            if (!comp || comp.is('wrapper')) return;
            e.preventDefault();
            editor.select(comp);
            const frameRect =
                editor.Canvas.getFrameEl().getBoundingClientRect();
            setMenu({
                x: frameRect.left + e.clientX,
                y: frameRect.top + e.clientY,
                component: comp,
            });
        };
        const attachContext = () => {
            frameDoc?.removeEventListener('contextmenu', onContext);
            frameDoc = editor.Canvas.getDocument() ?? null;
            frameDoc?.addEventListener('contextmenu', onContext);
        };
        // Dokument ramki płótna powstaje dopiero po załadowaniu, więc podpinamy się po zdarzeniach ładowania.
        editor.on('canvas:frame:load', attachContext);
        editor.on('load', attachContext);
        attachContext();

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

            adding.current = true;
            window.setTimeout(() => (adding.current = false), 500);
            const added =
                editor.getWrapper()?.append(block.getContent() as string) ?? [];
            if (added[0]) {
                editor.select(added[0]);
                if (added[0].is('lm-block')) openBlockSettings(added[0]);
                editor.Canvas.scrollTo(added[0], { behavior: 'smooth' });
            }
        };
        blocksContainer.addEventListener('click', addBlock, true);

        editorRef.current = editor;
        setEditorInst(editor);
        refresh();
        setDirty(false);

        return () => {
            blocksContainer.removeEventListener('click', addBlock, true);
            frameDoc?.removeEventListener('contextmenu', onContext);
            editor.destroy();
            editorRef.current = null;
            setEditorInst(null);
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
            {menu && editorInst && (
                <BuilderContextMenu
                    editor={editorInst}
                    component={menu.component}
                    x={menu.x}
                    y={menu.y}
                    onClose={() => setMenu(null)}
                />
            )}
            <Head title={t('Edytor: {name}', { name: content.title })} />
            <div className="lumin-builder fixed inset-0 overflow-hidden bg-neutral-200">
                <div
                    ref={canvas}
                    className="absolute inset-x-0 top-14 bottom-0"
                />

                {empty && (
                    <div className="pointer-events-none absolute inset-x-0 top-14 bottom-0 z-[55] flex items-center justify-center">
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

                {/* Pasek górny: jeden biały pasek, strona zaczyna się pod nim */}
                <header className="absolute inset-x-0 top-0 z-[60] grid h-14 grid-cols-[1fr_auto_1fr] items-center gap-3 border-b bg-white px-3">
                    <div className="flex min-w-0 items-center gap-1">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-9"
                        >
                            <Link
                                href={cmsUrl.contentEdit(type.key, content.id)}
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
                                <span
                                    className="text-xs text-amber-600"
                                    title={
                                        autosavedAt
                                            ? `${t('Autozapis')}: ${formatRevisionDate(autosavedAt)}`
                                            : undefined
                                    }
                                >
                                    {t('niezapisane')}
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-0.5">
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

                    <div className="flex items-center justify-end gap-1.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setTemplatesOpen(true)}
                        >
                            <LayoutTemplate /> {t('Szablony')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-9"
                            aria-label={t('Historia wersji')}
                            title={t('Historia wersji')}
                            onClick={() => setHistoryOpen(true)}
                        >
                            <History />
                        </Button>
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
                </header>

                <div className="pointer-events-none absolute inset-x-0 top-14 bottom-0 z-[60] flex flex-col gap-3 p-3">
                    {/* Panele boczne */}
                    <div className="flex min-h-0 flex-1 items-end justify-start gap-3">
                        <div className="flex max-h-full min-h-0 flex-col items-start justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={t('Panel')}
                                title={t('Panel')}
                                className="pointer-events-auto size-9 bg-white"
                                onClick={() => setPanelOpen((o) => !o)}
                            >
                                <PanelLeft />
                            </Button>
                            <div
                                className={cn(
                                    panelClass,
                                    'h-[36rem] max-h-full min-h-0',
                                    selected?.isRichText && tab === 'props'
                                        ? 'w-[27rem]'
                                        : 'w-80',
                                    !panelOpen && 'hidden',
                                )}
                            >
                                <div className="flex border-b p-1.5">
                                    {[
                                        {
                                            id: 'blocks',
                                            label: t('Bloki'),
                                            icon: LayoutGrid,
                                        },
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
                                                setTab(id as typeof tab)
                                            }
                                            title={label}
                                            aria-label={label}
                                            className={cn(
                                                'flex items-center justify-center gap-1.5 rounded-lg py-1.5 text-sm',
                                                tab === id
                                                    ? 'flex-[2.2] bg-brand-soft font-medium text-brand-foreground'
                                                    : 'flex-1 text-muted-foreground hover:bg-muted',
                                            )}
                                        >
                                            <Icon className="size-4" />
                                            {tab === id && label}
                                        </button>
                                    ))}
                                </div>
                                <div
                                    className={cn(
                                        'flex min-h-0 flex-1 flex-col',
                                        tab !== 'blocks' && 'hidden',
                                    )}
                                >
                                    <div className="flex items-center gap-1.5 border-b px-1.5 py-1">
                                        <div className="relative min-w-0 flex-1">
                                            <Search className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                type="search"
                                                value={blockQuery}
                                                onChange={(e) =>
                                                    setBlockQuery(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={t(
                                                    'Szukaj bloków...',
                                                )}
                                                aria-label={t(
                                                    'Szukaj bloków...',
                                                )}
                                                className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-7 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setManageOpen(true)}
                                        >
                                            <Bookmark /> {t('Moje bloki')}
                                        </Button>
                                    </div>
                                    <div
                                        ref={blocksEl}
                                        className="min-h-0 flex-1 overflow-y-auto"
                                    />
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
                                    {selected?.isBlock && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mb-2 w-full"
                                            onClick={() => {
                                                const c =
                                                    editorRef.current?.getSelected();
                                                if (c) openBlockSettings(c);
                                            }}
                                        >
                                            <Settings2 />{' '}
                                            {t('Ustawienia bloku')}
                                        </Button>
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
                                    {selected && !selected.isWrapper && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mb-2 w-full"
                                            onClick={() =>
                                                setSaveBlockOpen(true)
                                            }
                                        >
                                            <BookmarkPlus />{' '}
                                            {t('Zapisz jako blok')}
                                        </Button>
                                    )}
                                    <RichTextPanel
                                        editor={editorInst}
                                        onChange={() => setDirty(true)}
                                    />
                                    <FieldBinding
                                        editor={editorInst}
                                        fields={customFields}
                                        onChange={() => setDirty(true)}
                                    />
                                    <DisplayConditions
                                        editor={editorInst}
                                        fields={customFields}
                                        onChange={() => setDirty(true)}
                                    />
                                    <div ref={traitsEl} />
                                    <StylePanel
                                        editor={editorInst}
                                        device={device as Device}
                                        onChange={() => setDirty(true)}
                                    />
                                    <LocalStylesPanel
                                        editor={editorInst}
                                        deviceLabel={
                                            devices.find((d) => d.id === device)
                                                ?.label ?? ''
                                        }
                                        onChange={() => setDirty(true)}
                                    />
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

                <PageTemplatesDialog
                    open={templatesOpen}
                    hasContent={(editorInst?.getComponents().length ?? 0) > 0}
                    onOpenChange={setTemplatesOpen}
                    onInsert={(html, mode) => {
                        if (!editorInst) return;

                        if (mode === 'replace') editorInst.setComponents(html);
                        else editorInst.addComponents(html);

                        setDirty(true);
                    }}
                />

                <HistoryDialog
                    open={historyOpen}
                    baseUrl={apiBase}
                    onOpenChange={setHistoryOpen}
                    onRestore={restoreRevision}
                />

                {saveBlockOpen && (
                    <SaveBlockDialog
                        open
                        defaultName={selected?.name ?? ''}
                        saving={savingBlock}
                        onOpenChange={setSaveBlockOpen}
                        onSave={(name) => void saveAsBlock(name)}
                    />
                )}

                <ManageBlocksDialog
                    open={manageOpen}
                    blocks={saved}
                    onOpenChange={setManageOpen}
                    onDelete={(b) => void deleteBlock(b)}
                />

                {blockDialog && (
                    <ThemeBlockDialog
                        block={blockDialog.block}
                        values={blockDialog.values}
                        fields={customFields}
                        onApply={applyBlockSettings}
                        onClose={() => setBlockDialog(null)}
                    />
                )}

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
