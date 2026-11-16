import { Extension } from '@tiptap/core';
import CharacterCount from '@tiptap/extension-character-count';
import Highlight from '@tiptap/extension-highlight';
import Image from '@tiptap/extension-image';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import {
    Table,
    TableCell,
    TableHeader,
    TableRow,
} from '@tiptap/extension-table';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Code2,
    Ellipsis,
    Heading,
    Highlighter,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Table as TableIcon,
    Rows3,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { MediaPicker } from '@/components/cms/media-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types/cms';

type Props = {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    toolbar?: 'full' | 'basic';
    minHeight?: number;
};

/**
 * Wyrównanie akapitów i nagłówków przez klasy motywu (lm-center, lm-right), a nie przez style inline,
 * bo serwer usuwa atrybut style.
 */
const Align = Extension.create({
    name: 'lmAlign',
    addGlobalAttributes() {
        return [
            {
                types: ['paragraph', 'heading'],
                attributes: {
                    align: {
                        default: null,
                        parseHTML: (el: HTMLElement) =>
                            el.classList.contains('lm-center')
                                ? 'center'
                                : el.classList.contains('lm-right')
                                  ? 'right'
                                  : null,
                        renderHTML: (attrs: Record<string, unknown>) =>
                            typeof attrs.align === 'string'
                                ? { class: `lm-${attrs.align}` }
                                : {},
                    },
                },
            },
        ];
    },
});

function Btn({
    label,
    active,
    disabled,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            aria-pressed={active}
            disabled={disabled}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className={cn(
                'rounded p-1.5 hover:bg-muted disabled:opacity-40 [&_svg]:size-4',
                active && 'bg-brand/15 text-brand',
            )}
        >
            {children}
        </button>
    );
}

/** Przycisk-ikona otwierający małe menu (format, wyrównanie, tabela). */
function MenuBtn({
    label,
    active,
    icon,
    children,
}: {
    label: string;
    active?: boolean;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    title={label}
                    onMouseDown={(e) => e.preventDefault()}
                    className={cn(
                        'rounded p-1.5 hover:bg-muted [&_svg]:size-4',
                        active && 'bg-brand/15 text-brand',
                    )}
                >
                    {icon}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">{children}</DropdownMenuContent>
        </DropdownMenu>
    );
}

function Sep() {
    return <span aria-hidden className="mx-1 my-1 w-px bg-border" />;
}

function setAlign(editor: Editor, align: 'center' | 'right' | null) {
    editor
        .chain()
        .focus()
        .updateAttributes('paragraph', { align })
        .updateAttributes('heading', { align })
        .run();
}

function currentAlign(editor: Editor): string | null {
    return (
        (editor.getAttributes('paragraph').align as string | null) ??
        (editor.getAttributes('heading').align as string | null) ??
        null
    );
}

function blockValue(editor: Editor): string {
    for (const level of [2, 3, 4]) {
        if (editor.isActive('heading', { level })) return `h${level}`;
    }

    return 'p';
}

function LinkPanel({
    editor,
    onClose,
}: {
    editor: Editor;
    onClose: () => void;
}) {
    const attrs = editor.getAttributes('link');
    const [url, setUrl] = useState<string>((attrs.href as string) ?? '');
    const [blank, setBlank] = useState(attrs.target === '_blank');

    const apply = () => {
        const href = url.trim();
        const chain = editor.chain().focus().extendMarkRange('link');

        if (href === '') {
            chain.unsetLink().run();
        } else {
            chain.setLink({ href, target: blank ? '_blank' : null }).run();
        }

        onClose();
    };

    return (
        <div className="flex flex-wrap items-center gap-3 border-b bg-muted/20 p-2">
            <Input
                autoFocus
                value={url}
                placeholder="https://"
                aria-label={t('Adres linku')}
                className="h-8 max-w-sm flex-1"
                onChange={(e) => setUrl(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        apply();
                    }
                    if (e.key === 'Escape') onClose();
                }}
            />
            <Label className="flex items-center gap-2 text-sm font-normal">
                <Checkbox
                    checked={blank}
                    onCheckedChange={(v) => setBlank(v === true)}
                />
                {t('Otwórz w nowej karcie')}
            </Label>
            <Button type="button" size="sm" onClick={apply}>
                {t('Zastosuj')}
            </Button>
            {editor.isActive('link') && (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run();
                        onClose();
                    }}
                >
                    {t('Usuń link')}
                </Button>
            )}
            <Button type="button" size="sm" variant="ghost" onClick={onClose}>
                {t('Anuluj')}
            </Button>
        </div>
    );
}

/** Edytor tekstu sformatowanego (TipTap). Serwer i tak czyści HTML przy zapisie. */
export function WysiwygEditor({
    id,
    value,
    onChange,
    toolbar = 'full',
    minHeight = 200,
}: Props) {
    const [linkOpen, setLinkOpen] = useState(false);
    const [mediaOpen, setMediaOpen] = useState(false);
    const [more, setMore] = useState(false);

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: {
                    openOnClick: false,
                    HTMLAttributes: { target: null, rel: null },
                },
            }),
            Align,
            Highlight,
            Subscript,
            Superscript,
            Image.configure({ inline: false }),
            Table,
            TableRow,
            TableHeader,
            TableCell,
            CharacterCount,
        ],
        content: value || '',
        editorProps: {
            attributes: {
                ...(id ? { id } : {}),
                class: 'wysiwyg-content px-3 py-2 outline-none',
                style: `min-height:${minHeight}px`,
            },
        },
        onUpdate: ({ editor: e }) => onChange(e.isEmpty ? '' : e.getHTML()),
    });

    if (!editor) return <div className="h-32 rounded-md border" />;

    const full = toolbar === 'full';
    const align = currentAlign(editor);
    const inTable = editor.isActive('table');

    const insertImage = (items: MediaItem[]) => {
        const item = items[0];

        if (!item) return;

        const size = item.sizes?.large ?? item.sizes?.medium_large ?? null;
        editor
            .chain()
            .focus()
            .setImage({
                src: size?.url ?? item.url,
                alt: item.alt || '',
                title: item.title || undefined,
                width: size?.width ?? item.width ?? undefined,
                height: size?.height ?? item.height ?? undefined,
            })
            .run();
    };

    const AlignIcon =
        align === 'center'
            ? AlignCenter
            : align === 'right'
              ? AlignRight
              : AlignLeft;

    return (
        <div className="rounded-md border focus-within:ring-[3px] focus-within:ring-ring/30">
            <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 p-1">
                {full && (
                    <MenuBtn
                        label={t('Format')}
                        active={blockValue(editor) !== 'p'}
                        icon={<Heading />}
                    >
                        <DropdownMenuItem
                            onSelect={() =>
                                editor.chain().focus().setParagraph().run()
                            }
                        >
                            {t('Akapit')}
                        </DropdownMenuItem>
                        {([2, 3, 4] as const).map((level) => (
                            <DropdownMenuItem
                                key={level}
                                onSelect={() =>
                                    editor
                                        .chain()
                                        .focus()
                                        .setHeading({ level })
                                        .run()
                                }
                            >
                                {t('Nagłówek {n}', { n: level })}
                            </DropdownMenuItem>
                        ))}
                    </MenuBtn>
                )}
                <Btn
                    label={t('Pogrubienie')}
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <Bold />
                </Btn>
                <Btn
                    label={t('Kursywa')}
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <Italic />
                </Btn>
                <Btn
                    label={t('Lista punktowana')}
                    active={editor.isActive('bulletList')}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                >
                    <List />
                </Btn>
                <Btn
                    label={t('Lista numerowana')}
                    active={editor.isActive('orderedList')}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered />
                </Btn>
                {full && (
                    <>
                        <Btn
                            label={t('Cytat')}
                            active={editor.isActive('blockquote')}
                            onClick={() =>
                                editor.chain().focus().toggleBlockquote().run()
                            }
                        >
                            <Quote />
                        </Btn>
                        <MenuBtn
                            label={t('Wyrównanie')}
                            active={align !== null}
                            icon={<AlignIcon />}
                        >
                            <DropdownMenuItem
                                onSelect={() => setAlign(editor, null)}
                            >
                                <AlignLeft /> {t('Wyrównaj do lewej')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => setAlign(editor, 'center')}
                            >
                                <AlignCenter /> {t('Wyśrodkuj')}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => setAlign(editor, 'right')}
                            >
                                <AlignRight /> {t('Wyrównaj do prawej')}
                            </DropdownMenuItem>
                        </MenuBtn>
                    </>
                )}
                <Btn
                    label={t('Link')}
                    active={editor.isActive('link') || linkOpen}
                    onClick={() => setLinkOpen((o) => !o)}
                >
                    <Link2 />
                </Btn>
                {full && (
                    <>
                        <Btn
                            label={t('Obraz z biblioteki mediów')}
                            onClick={() => setMediaOpen(true)}
                        >
                            <ImageIcon />
                        </Btn>
                        <Btn
                            label={t('Wstaw tabelę')}
                            active={inTable}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .insertTable({
                                        rows: 3,
                                        cols: 3,
                                        withHeaderRow: true,
                                    })
                                    .run()
                            }
                        >
                            <TableIcon />
                        </Btn>
                        {inTable && (
                            <MenuBtn
                                label={t('Edytuj tabelę')}
                                active
                                icon={<Rows3 />}
                            >
                                {(
                                    [
                                        [t('Dodaj wiersz'), 'addRowAfter'],
                                        [t('Dodaj kolumnę'), 'addColumnAfter'],
                                        [t('Usuń wiersz'), 'deleteRow'],
                                        [t('Usuń kolumnę'), 'deleteColumn'],
                                        [
                                            t('Wiersz nagłówka'),
                                            'toggleHeaderRow',
                                        ],
                                        [t('Usuń tabelę'), 'deleteTable'],
                                    ] as const
                                ).map(([label, cmd]) => (
                                    <DropdownMenuItem
                                        key={cmd}
                                        onSelect={() =>
                                            editor.chain().focus()[cmd]().run()
                                        }
                                    >
                                        {label}
                                    </DropdownMenuItem>
                                ))}
                            </MenuBtn>
                        )}
                    </>
                )}
                <span className="ml-auto flex gap-0.5">
                    <Btn
                        label={t('Cofnij')}
                        disabled={!editor.can().undo()}
                        onClick={() => editor.chain().focus().undo().run()}
                    >
                        <Undo2 />
                    </Btn>
                    <Btn
                        label={t('Ponów')}
                        disabled={!editor.can().redo()}
                        onClick={() => editor.chain().focus().redo().run()}
                    >
                        <Redo2 />
                    </Btn>
                    {full && (
                        <Btn
                            label={t('Więcej narzędzi')}
                            active={more}
                            onClick={() => setMore((m) => !m)}
                        >
                            <Ellipsis />
                        </Btn>
                    )}
                </span>
            </div>
            {full && more && (
                <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/20 p-1">
                    <Btn
                        label={t('Podkreślenie')}
                        active={editor.isActive('underline')}
                        onClick={() =>
                            editor.chain().focus().toggleUnderline().run()
                        }
                    >
                        <UnderlineIcon />
                    </Btn>
                    <Btn
                        label={t('Przekreślenie')}
                        active={editor.isActive('strike')}
                        onClick={() =>
                            editor.chain().focus().toggleStrike().run()
                        }
                    >
                        <Strikethrough />
                    </Btn>
                    <Btn
                        label={t('Zakreślenie')}
                        active={editor.isActive('highlight')}
                        onClick={() =>
                            editor.chain().focus().toggleHighlight().run()
                        }
                    >
                        <Highlighter />
                    </Btn>
                    <Btn
                        label={t('Indeks górny')}
                        active={editor.isActive('superscript')}
                        onClick={() =>
                            editor.chain().focus().toggleSuperscript().run()
                        }
                    >
                        <SuperscriptIcon />
                    </Btn>
                    <Btn
                        label={t('Indeks dolny')}
                        active={editor.isActive('subscript')}
                        onClick={() =>
                            editor.chain().focus().toggleSubscript().run()
                        }
                    >
                        <SubscriptIcon />
                    </Btn>
                    <Sep />
                    <Btn
                        label={t('Kod')}
                        active={editor.isActive('code')}
                        onClick={() =>
                            editor.chain().focus().toggleCode().run()
                        }
                    >
                        <Code />
                    </Btn>
                    <Btn
                        label={t('Blok kodu')}
                        active={editor.isActive('codeBlock')}
                        onClick={() =>
                            editor.chain().focus().toggleCodeBlock().run()
                        }
                    >
                        <Code2 />
                    </Btn>
                    <Btn
                        label={t('Linia pozioma')}
                        onClick={() =>
                            editor.chain().focus().setHorizontalRule().run()
                        }
                    >
                        <Minus />
                    </Btn>
                    <Sep />
                    <Btn
                        label={t('Wyczyść formatowanie')}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .unsetAllMarks()
                                .clearNodes()
                                .run()
                        }
                    >
                        <RemoveFormatting />
                    </Btn>
                </div>
            )}
            {linkOpen && (
                <LinkPanel editor={editor} onClose={() => setLinkOpen(false)} />
            )}
            <EditorContent editor={editor} />
            {full && (
                <div className="border-t px-3 py-1.5 text-xs text-muted-foreground">
                    {t('{words} słów · {chars} znaków', {
                        words: editor.storage.characterCount.words(),
                        chars: editor.storage.characterCount.characters(),
                    })}
                </div>
            )}
            <MediaPicker
                open={mediaOpen}
                onOpenChange={setMediaOpen}
                imagesOnly
                onPick={(items) => {
                    insertImage(items);
                    setMediaOpen(false);
                }}
            />
        </div>
    );
}
