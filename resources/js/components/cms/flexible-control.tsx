import {
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Copy,
    Plus,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { FieldList } from '@/components/cms/fields';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import type { FieldDefinition, MediaItem } from '@/types/cms';

type Row = Record<string, unknown>;
type Layout = {
    name: string;
    label: string;
    min?: number | string | null;
    max?: number | string | null;
    sub_fields: FieldDefinition[];
};

type Props = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    media: Record<number, MediaItem>;
    rememberMedia: (items: MediaItem[]) => void;
    errors: Record<string, string | undefined>;
    errorKey: string;
    groupId?: number;
    path: string;
};

const LAYOUT_KEY = '_layout';
let seq = 0;
const newKey = () => `x${seq++}`;

/** Wiersze o różnych układach: każdy wiersz ma wybrany układ i jego własne pola. */
export function FlexibleControl({
    field,
    value,
    onChange,
    media,
    rememberMedia,
    errors,
    errorKey,
    groupId,
    path,
}: Props) {
    const layouts = (
        Array.isArray(field.layouts) ? field.layouts : []
    ) as Layout[];
    const byName = new Map(layouts.map((l) => [l.name, l]));
    const rows: Row[] = Array.isArray(value) ? (value as Row[]) : [];
    const max = Number(field.max) || 200;
    const min = Number(field.min) || 0;

    const keys = useRef<string[]>([]);
    while (keys.current.length < rows.length) keys.current.push(newKey());
    keys.current.length = rows.length;
    const [closed, setClosed] = useState<Record<string, boolean>>({});

    const countOf = (name: string) =>
        rows.filter((r) => r[LAYOUT_KEY] === name).length;
    const canAdd = (l: Layout) =>
        rows.length < max &&
        (!Number(l.max) || countOf(l.name) < Number(l.max));

    const add = (l: Layout) => {
        keys.current.push(newKey());
        onChange([...rows, { [LAYOUT_KEY]: l.name }]);
    };
    const remove = (i: number) => {
        keys.current.splice(i, 1);
        onChange(rows.filter((_, j) => j !== i));
    };
    const move = (i: number, dir: -1 | 1) => {
        const next = [...rows];
        const k = [...keys.current];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        [k[i], k[i + dir]] = [k[i + dir], k[i]];
        keys.current = k;
        onChange(next);
    };
    const duplicate = (i: number) => {
        const l = byName.get(String(rows[i][LAYOUT_KEY]));
        if (!l || !canAdd(l)) return;
        keys.current.splice(i + 1, 0, newKey());
        const next = [...rows];
        next.splice(i + 1, 0, structuredClone(rows[i]));
        onChange(next);
    };
    const setRowValue = (i: number, name: string, v: unknown) =>
        onChange(rows.map((r, j) => (j === i ? { ...r, [name]: v } : r)));

    const addable = layouts.filter(canAdd);
    const label = String(field.button_label || t('Dodaj układ'));

    return (
        <div className="space-y-3">
            {rows.length === 0 && (
                <p className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                    {t('Brak wierszy.')}
                </p>
            )}
            {rows.map((row, i) => {
                const key = keys.current[i];
                const layout = byName.get(String(row[LAYOUT_KEY]));
                const isClosed = closed[key];

                return (
                    <div key={key} className="rounded-lg border bg-card/50">
                        <div
                            className={`flex items-center gap-1 px-2 py-1.5 ${isClosed ? '' : 'border-b'}`}
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    setClosed((c) => ({ ...c, [key]: !c[key] }))
                                }
                                className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1 text-left text-sm font-medium hover:bg-muted"
                            >
                                {isClosed ? (
                                    <ChevronRight className="size-4 shrink-0" />
                                ) : (
                                    <ChevronDown className="size-4 shrink-0" />
                                )}
                                <span className="w-6 shrink-0 text-xs text-muted-foreground tabular-nums">
                                    {i + 1}
                                </span>
                                <span className="truncate">
                                    {layout
                                        ? t(layout.label)
                                        : t('Nieznany układ')}
                                </span>
                            </button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('W górę')}
                                disabled={i === 0}
                                onClick={() => move(i, -1)}
                            >
                                <ChevronUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('W dół')}
                                disabled={i === rows.length - 1}
                                onClick={() => move(i, 1)}
                            >
                                <ChevronDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Duplikuj wiersz')}
                                disabled={!layout || !canAdd(layout)}
                                onClick={() => duplicate(i)}
                            >
                                <Copy />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Usuń wiersz')}
                                className="text-destructive"
                                disabled={rows.length <= min}
                                onClick={() => remove(i)}
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        {!isClosed && layout && (
                            <div className="p-3">
                                <FieldList
                                    fields={layout.sub_fields}
                                    values={row}
                                    errors={errors}
                                    errorKey={`${errorKey}.${i}`}
                                    media={media}
                                    rememberMedia={rememberMedia}
                                    groupId={groupId}
                                    path={`${path}.${layout.name}`}
                                    onChange={(name, v) =>
                                        setRowValue(i, name, v)
                                    }
                                />
                            </div>
                        )}
                    </div>
                );
            })}

            {layouts.length <= 1 ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={addable.length === 0}
                    onClick={() => addable[0] && add(addable[0])}
                >
                    <Plus /> {label}
                </Button>
            ) : (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={addable.length === 0}
                        >
                            <Plus /> {label}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        {addable.map((l) => (
                            <DropdownMenuItem
                                key={l.name}
                                onSelect={() => add(l)}
                            >
                                {t(l.label)}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}
