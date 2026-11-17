import type { Component, Editor } from 'grapesjs';

/** Operacje na elemencie wspólne dla menu prawego przycisku i skrótów klawiszowych. */

/** Schowek na wygląd (klasy) między elementami; niezależny od schowka systemowego. */
let styleClipboard: string[] | null = null;

export const hasCopiedStyle = () => styleClipboard !== null;

const isEditing = (editor: Editor) => !!editor.getEditing();

const usable = (c: Component | undefined): c is Component =>
    !!c && !c.is('wrapper');

export const canRemove = (c: Component) => c.get('removable') !== false;

export const canMove = (c: Component, dir: -1 | 1) => {
    const coll = c.collection;
    if (!coll || c.get('draggable') === false) return false;
    const i = c.index();

    return dir < 0 ? i > 0 : i < coll.length - 1;
};

export function duplicate(editor: Editor, c: Component) {
    if (!usable(c) || c.get('copyable') === false || !c.collection) return;
    const clone = c.collection.add(c.clone(), { at: c.index() + 1 });
    const added = Array.isArray(clone) ? clone[0] : clone;
    if (added) editor.select(added);
}

export function remove(editor: Editor, c: Component) {
    if (!usable(c) || !canRemove(c)) return;
    const parent = c.parent();
    c.remove();
    if (parent && !parent.is('wrapper')) editor.select(parent);
}

export function move(editor: Editor, c: Component, dir: -1 | 1) {
    const parent = c.parent();
    if (!usable(c) || !parent || !canMove(c, dir)) return;
    c.move(parent, { at: c.index() + dir });
    editor.select(c);
}

export function selectParent(editor: Editor, c: Component) {
    const parent = c.parent();
    if (parent && !parent.is('wrapper')) editor.select(parent);
}

export function copyStyle(c: Component) {
    if (!usable(c)) return false;
    styleClipboard = c.getClasses().filter(Boolean);

    return true;
}

export function pasteStyle(c: Component) {
    if (!usable(c) || styleClipboard === null) return false;
    c.setClass(styleClipboard);

    return true;
}

export function copy(editor: Editor) {
    editor.runCommand('core:copy');
}

export function paste(editor: Editor) {
    editor.runCommand('core:paste');
}

/** Skróty klawiszowe edytora (zapis ma osobną rejestrację w builder.tsx). */
export function registerShortcuts(editor: Editor) {
    const on = (
        id: string,
        keys: string,
        run: (c: Component) => void,
        opts: { allowEditing?: boolean } = {},
    ) =>
        editor.Keymaps.add(
            id,
            keys,
            (_ed, _s, e) => {
                const target = e?.target as HTMLElement | undefined;
                if (
                    isEditing(editor) ||
                    /^(input|textarea|select)$/i.test(target?.tagName ?? '')
                ) {
                    return;
                }
                const c = editor.getSelected();
                if (!usable(c)) return;
                if (typeof e?.preventDefault === 'function') e.preventDefault();
                run(c);
            },
            { prevent: false, ...opts },
        );

    on('lumin-duplicate', '⌘+d, ctrl+d', (c) => duplicate(editor, c));
    on('lumin-move-up', 'alt+up', (c) => move(editor, c, -1));
    on('lumin-move-down', 'alt+down', (c) => move(editor, c, 1));
    on('lumin-select-parent', 'ctrl+up, ⌘+up', (c) => selectParent(editor, c));
    on('lumin-copy-style', '⌘+alt+c, ctrl+alt+c', (c) => copyStyle(c));
    on('lumin-paste-style', '⌘+alt+v, ctrl+alt+v', (c) => pasteStyle(c));
}

const TRASH =
    '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>';

/** Przycisk usuwania elementu w panelu warstw (pokazuje się po najechaniu na warstwę). */
export function registerLayerDelete(editor: Editor, label: string) {
    editor.on(
        'layer:render',
        ({ component, el }: { component: Component; el: HTMLElement }) => {
            const title = el.querySelector<HTMLElement>(
                ':scope > .gjs-layer-item',
            );
            if (
                !title ||
                title.querySelector('.lm-layer-del') ||
                !usable(component) ||
                !canRemove(component)
            )
                return;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'lm-layer-del';
            button.title = label;
            button.setAttribute('aria-label', label);
            button.innerHTML = TRASH;
            // mousedown nie może zaznaczyć ani przeciągnąć warstwy.
            button.addEventListener('mousedown', (e) => e.stopPropagation());
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                remove(editor, component);
            });
            title.append(button);
        },
    );
}
