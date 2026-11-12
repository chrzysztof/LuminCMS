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
import { cn } from '@/lib/utils';
import type { FieldDefinition, MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

type Row = Record<string, unknown>;

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

let seq = 0;
const newKey = () => `r${seq++}`;

/** Edytor wierszy: dodawanie, kolejność, duplikowanie, zwijanie. Każdy wiersz to osobny formularz pól podrzędnych. */
export function RepeaterControl({
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
    const rows: Row[] = Array.isArray(value) ? (value as Row[]) : [];
    const subFields = (
        Array.isArray(field.sub_fields) ? field.sub_fields : []
    ) as FieldDefinition[];
    const max = Number(field.max) || 200;
    const min = Number(field.min) || 0;
    const layout = String(field.layout ?? 'block');
    const titleField = String(field.collapsed ?? '');

    // Stabilne klucze wierszy, żeby edytory w wierszach nie traciły stanu przy zmianie kolejności.
    const keys = useRef<string[]>([]);
    while (keys.current.length < rows.length) keys.current.push(newKey());
    keys.current.length = rows.length;
    const [closed, setClosed] = useState<Record<string, boolean>>({});

    const emit = (next: Row[]) => onChange(next);
    const add = () => {
        keys.current.push(newKey());
        emit([...rows, {}]);
    };
    const remove = (i: number) => {
        keys.current.splice(i, 1);
        emit(rows.filter((_, j) => j !== i));
    };
    const move = (i: number, dir: -1 | 1) => {
        const next = [...rows];
        const k = [...keys.current];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        [k[i], k[i + dir]] = [k[i + dir], k[i]];
        keys.current = k;
        emit(next);
    };
    const duplicate = (i: number) => {
        if (rows.length >= max) return;
        keys.current.splice(i + 1, 0, newKey());
        const next = [...rows];
        next.splice(i + 1, 0, structuredClone(rows[i]));
        emit(next);
    };
    const setRowValue = (i: number, name: string, v: unknown) =>
        emit(rows.map((r, j) => (j === i ? { ...r, [name]: v } : r)));

    const rowTitle = (row: Row, i: number) => {
        const raw = titleField ? row[titleField] : null;
        const text =
            typeof raw === 'string' ? raw.replace(/<[^>]+>/g, '').trim() : '';

        return text ? text.slice(0, 60) : t('Wiersz {n}', { n: i + 1 });
    };

    return (
        <div className="space-y-3">
            {rows.length === 0 && (
                <p className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                    {t('Brak wierszy.')}
                </p>
            )}
            {rows.map((row, i) => {
                const key = keys.current[i];
                const collapsible = layout === 'block';
                const isClosed = collapsible && closed[key];

                return (
                    <div key={key} className="rounded-lg border bg-card/50">
                        <div
                            className={cn(
                                'flex items-center gap-1 px-2 py-1.5',
                                !isClosed && layout === 'block' && 'border-b',
                            )}
                        >
                            <button
                                type="button"
                                disabled={!collapsible}
                                onClick={() =>
                                    setClosed((c) => ({
                                        ...c,
                                        [key]: !c[key],
                                    }))
                                }
                                className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1 text-left text-sm font-medium hover:bg-muted disabled:hover:bg-transparent"
                            >
                                {collapsible &&
                                    (isClosed ? (
                                        <ChevronRight className="size-4 shrink-0" />
                                    ) : (
                                        <ChevronDown className="size-4 shrink-0" />
                                    ))}
                                <span className="w-6 shrink-0 text-xs text-muted-foreground tabular-nums">
                                    {i + 1}
                                </span>
                                <span className="truncate">
                                    {rowTitle(row, i)}
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
                                disabled={rows.length >= max}
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
                        {!isClosed && (
                            <div className="p-3">
                                <FieldList
                                    fields={subFields}
                                    values={row}
                                    errors={errors}
                                    errorKey={`${errorKey}.${i}`}
                                    media={media}
                                    rememberMedia={rememberMedia}
                                    groupId={groupId}
                                    path={path}
                                    onChange={(name, v) =>
                                        setRowValue(i, name, v)
                                    }
                                />
                            </div>
                        )}
                    </div>
                );
            })}
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={rows.length >= max}
                onClick={add}
            >
                <Plus /> {String(field.button_label || t('Dodaj wiersz'))}
            </Button>
        </div>
    );
}
