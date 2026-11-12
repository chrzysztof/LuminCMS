import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    toolbar?: 'full' | 'basic';
    minHeight?: number;
};

function Btn({
    label,
    active,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            aria-pressed={active}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className={cn(
                'rounded p-1.5 hover:bg-muted [&_svg]:size-4',
                active && 'bg-brand/15 text-brand',
            )}
        >
            {children}
        </button>
    );
}

function setLink(editor: Editor) {
    const previous = (editor.getAttributes('link').href as string) ?? '';
    const url = window.prompt('Adres linku (puste usuwa link)', previous);

    if (url === null) return;

    if (url.trim() === '') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    }
}

/** Edytor tekstu sformatowanego (TipTap). Serwer i tak czyści HTML przy zapisie. */
export function WysiwygEditor({
    id,
    value,
    onChange,
    toolbar = 'full',
    minHeight = 200,
}: Props) {
    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: { openOnClick: false },
            }),
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

    return (
        <div className="rounded-md border focus-within:ring-[3px] focus-within:ring-ring/30">
            <div className="flex flex-wrap gap-0.5 border-b bg-muted/40 p-1">
                <Btn
                    label="Pogrubienie"
                    active={editor.isActive('bold')}
                    onClick={() => editor.chain().focus().toggleBold().run()}
                >
                    <Bold />
                </Btn>
                <Btn
                    label="Kursywa"
                    active={editor.isActive('italic')}
                    onClick={() => editor.chain().focus().toggleItalic().run()}
                >
                    <Italic />
                </Btn>
                {full && (
                    <>
                        <Btn
                            label="Podkreślenie"
                            active={editor.isActive('underline')}
                            onClick={() =>
                                editor.chain().focus().toggleUnderline().run()
                            }
                        >
                            <UnderlineIcon />
                        </Btn>
                        <Btn
                            label="Przekreślenie"
                            active={editor.isActive('strike')}
                            onClick={() =>
                                editor.chain().focus().toggleStrike().run()
                            }
                        >
                            <Strikethrough />
                        </Btn>
                        <Btn
                            label="Nagłówek 2"
                            active={editor.isActive('heading', { level: 2 })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 2 })
                                    .run()
                            }
                        >
                            <Heading2 />
                        </Btn>
                        <Btn
                            label="Nagłówek 3"
                            active={editor.isActive('heading', { level: 3 })}
                            onClick={() =>
                                editor
                                    .chain()
                                    .focus()
                                    .toggleHeading({ level: 3 })
                                    .run()
                            }
                        >
                            <Heading3 />
                        </Btn>
                    </>
                )}
                <Btn
                    label="Lista punktowana"
                    active={editor.isActive('bulletList')}
                    onClick={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                >
                    <List />
                </Btn>
                <Btn
                    label="Lista numerowana"
                    active={editor.isActive('orderedList')}
                    onClick={() =>
                        editor.chain().focus().toggleOrderedList().run()
                    }
                >
                    <ListOrdered />
                </Btn>
                <Btn
                    label="Link"
                    active={editor.isActive('link')}
                    onClick={() => setLink(editor)}
                >
                    <Link2 />
                </Btn>
                {full && (
                    <>
                        <Btn
                            label="Cytat"
                            active={editor.isActive('blockquote')}
                            onClick={() =>
                                editor.chain().focus().toggleBlockquote().run()
                            }
                        >
                            <Quote />
                        </Btn>
                        <Btn
                            label="Kod"
                            active={editor.isActive('code')}
                            onClick={() =>
                                editor.chain().focus().toggleCode().run()
                            }
                        >
                            <Code />
                        </Btn>
                        <Btn
                            label="Linia pozioma"
                            onClick={() =>
                                editor.chain().focus().setHorizontalRule().run()
                            }
                        >
                            <Minus />
                        </Btn>
                    </>
                )}
                <span className="ml-auto flex gap-0.5">
                    <Btn
                        label="Cofnij"
                        onClick={() => editor.chain().focus().undo().run()}
                    >
                        <Undo2 />
                    </Btn>
                    <Btn
                        label="Ponów"
                        onClick={() => editor.chain().focus().redo().run()}
                    >
                        <Redo2 />
                    </Btn>
                </span>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
