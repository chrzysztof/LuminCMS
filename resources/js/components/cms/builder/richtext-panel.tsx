'use no memo';

import type { Editor } from 'grapesjs';
import { useEffect, useReducer } from 'react';
import { WysiwygEditor } from '@/components/cms/wysiwyg-editor';
import { t } from '@/lib/i18n';

/** Edytor tekstu sformatowanego dla zaznaczonego bloku "Akapit"; zmiany od razu widać na płótnie. */
export function RichTextPanel({
    editor,
    onChange,
}: {
    editor: Editor | null;
    onChange: () => void;
}) {
    const [, refresh] = useReducer((n: number) => n + 1, 0);

    useEffect(() => {
        if (!editor) return;
        editor.on('component:selected component:deselected', refresh);

        return () => {
            editor.off('component:selected component:deselected', refresh);
        };
    }, [editor]);

    const c = editor?.getSelected();

    if (!editor || !c || !c.is('lm-richtext')) return null;

    return (
        <div className="mb-3 space-y-1.5">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t('Treść akapitu')}
            </p>
            <WysiwygEditor
                key={c.getId() + String(c.cid)}
                value={String(c.get('richHtml') ?? '')}
                minHeight={180}
                onChange={(html) => {
                    c.set('richHtml', html);
                    onChange();
                }}
            />
        </div>
    );
}
