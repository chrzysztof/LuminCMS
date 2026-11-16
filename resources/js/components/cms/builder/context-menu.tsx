import type { Component, Editor } from 'grapesjs';
import {
    ArrowDown,
    ArrowUp,
    ClipboardPaste,
    Copy,
    CopyPlus,
    CornerLeftUp,
    Paintbrush,
    PaintBucket,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import {
    canMove,
    canRemove,
    copy,
    copyStyle,
    duplicate,
    hasCopiedStyle,
    move,
    paste,
    pasteStyle,
    remove,
    selectParent,
} from '@/lib/builder/actions';
import { t } from '@/lib/i18n';

type Props = {
    editor: Editor;
    component: Component;
    x: number;
    y: number;
    onClose: () => void;
};

const mod =
    typeof navigator !== 'undefined' && /Mac/i.test(navigator.platform)
        ? '⌘'
        : 'Ctrl+';

/** Menu prawego przycisku nad elementem na płótnie. Współrzędne są względem okna przeglądarki. */
export function BuilderContextMenu({
    editor,
    component,
    x,
    y,
    onClose,
}: Props) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const close = () => onClose();
        const key = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('mousedown', close);
        window.addEventListener('blur', close);
        window.addEventListener('keydown', key);
        const doc = editor.Canvas.getDocument();
        doc?.addEventListener('mousedown', close);
        doc?.addEventListener('scroll', close, true);

        return () => {
            window.removeEventListener('mousedown', close);
            window.removeEventListener('blur', close);
            window.removeEventListener('keydown', key);
            doc?.removeEventListener('mousedown', close);
            doc?.removeEventListener('scroll', close, true);
        };
    }, [editor, onClose]);

    const items: (
        | 'sep'
        | {
              label: string;
              icon: typeof Copy;
              hint?: string;
              disabled?: boolean;
              danger?: boolean;
              run: () => void;
          }
    )[] = [
        {
            label: t('Duplikuj'),
            icon: CopyPlus,
            hint: `${mod}D`,
            disabled: component.get('copyable') === false,
            run: () => duplicate(editor, component),
        },
        {
            label: t('Kopiuj'),
            icon: Copy,
            hint: `${mod}C`,
            run: () => copy(editor),
        },
        {
            label: t('Wklej'),
            icon: ClipboardPaste,
            hint: `${mod}V`,
            run: () => paste(editor),
        },
        'sep',
        {
            label: t('Kopiuj wygląd'),
            icon: Paintbrush,
            hint: `${mod}Alt+C`,
            run: () => copyStyle(component),
        },
        {
            label: t('Wklej wygląd'),
            icon: PaintBucket,
            hint: `${mod}Alt+V`,
            disabled: !hasCopiedStyle(),
            run: () => pasteStyle(component),
        },
        'sep',
        {
            label: t('Przenieś w górę'),
            icon: ArrowUp,
            hint: 'Alt+↑',
            disabled: !canMove(component, -1),
            run: () => move(editor, component, -1),
        },
        {
            label: t('Przenieś w dół'),
            icon: ArrowDown,
            hint: 'Alt+↓',
            disabled: !canMove(component, 1),
            run: () => move(editor, component, 1),
        },
        {
            label: t('Zaznacz element nadrzędny'),
            icon: CornerLeftUp,
            hint: `${mod}↑`,
            disabled:
                !component.parent() || !!component.parent()?.is('wrapper'),
            run: () => selectParent(editor, component),
        },
        'sep',
        {
            label: t('Usuń'),
            icon: Trash2,
            hint: 'Del',
            danger: true,
            disabled: !canRemove(component),
            run: () => remove(editor, component),
        },
    ];

    // Menu nie wychodzi poza okno.
    const left = Math.min(x, window.innerWidth - 240);
    const top = Math.min(y, window.innerHeight - 380);

    return (
        <div
            ref={ref}
            role="menu"
            className="fixed z-[100] w-56 rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
            style={{ left, top }}
            onMouseDown={(e) => e.stopPropagation()}
            onContextMenu={(e) => e.preventDefault()}
        >
            <div className="truncate px-2 py-1 text-xs text-muted-foreground">
                {component.getName()}
            </div>
            {items.map((item, i) =>
                item === 'sep' ? (
                    <div key={i} className="my-1 h-px bg-border" />
                ) : (
                    <button
                        key={item.label}
                        type="button"
                        role="menuitem"
                        disabled={item.disabled}
                        onClick={() => {
                            item.run();
                            onClose();
                        }}
                        className={`flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left hover:bg-accent disabled:pointer-events-none disabled:opacity-40 ${item.danger ? 'text-destructive' : ''}`}
                    >
                        <item.icon className="size-4" />
                        <span className="flex-1">{item.label}</span>
                        {item.hint && (
                            <span className="text-xs text-muted-foreground">
                                {item.hint}
                            </span>
                        )}
                    </button>
                ),
            )}
        </div>
    );
}
