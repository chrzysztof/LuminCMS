import type { Editor } from 'grapesjs';
import { icon } from '@/lib/builder/blocks';
import { t } from '@/lib/i18n';

export type SavedBlock = { id: number; name: string; html: string };

const PREFIX = 'saved-';

const escapeHtml = (text: string) =>
    text.replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c] as string,
    );

const BOOKMARK = icon(
    '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/>',
);

/** Podmienia własne bloki użytkownika w panelu bloków (kategoria "Moje bloki"). */
export function syncSavedBlocks(editor: Editor, blocks: SavedBlock[]) {
    editor.Blocks.getAll()
        .models.filter((b) => String(b.getId()).startsWith(PREFIX))
        .forEach((b) => editor.Blocks.remove(b.getId()));

    blocks.forEach((b) =>
        editor.Blocks.add(`${PREFIX}${b.id}`, {
            // Etykieta bloku jest wstawiana jako HTML, więc nazwę od użytkownika trzeba zabezpieczyć.
            label: escapeHtml(b.name),
            category: { id: 'saved', label: t('Moje bloki'), order: -1 },
            media: BOOKMARK,
            content: b.html,
            select: true,
        }),
    );
}
