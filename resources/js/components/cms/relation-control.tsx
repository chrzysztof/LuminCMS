import { ChevronDown, ChevronUp, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { api } from '@/lib/api';
import type { FieldDefinition } from '@/types/cms';

type Item = { id: number; label: string; meta: string | null };

type Props = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    groupId?: number;
    path: string;
};

const toIds = (v: unknown): number[] =>
    (Array.isArray(v) ? v : v ? [v] : [])
        .map(Number)
        .filter((n) => Number.isFinite(n) && n > 0);

/** Wybór rekordów (treści, terminy, użytkownicy) z wyszukiwarką; opcje pobierane z zapisanej definicji pola. */
export function RelationControl({
    field,
    value,
    onChange,
    groupId,
    path,
}: Props) {
    const multiple =
        field.type === 'relationship' ||
        (field.type === 'taxonomy'
            ? field.field_type !== 'select'
            : Boolean(field.multiple));
    const max = Number(field.max) || Infinity;
    const ids = toIds(value);
    const [labels, setLabels] = useState<Record<number, Item>>({});
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Item[]>([]);
    const [open, setOpen] = useState(false);
    const [failed, setFailed] = useState(false);
    const base = groupId ? `/cms/relations/${groupId}` : null;
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Etykiety wybranych pozycji (także tych, których nie ma w bieżących wynikach).
    const missing = ids.filter((id) => !labels[id]);
    const missingKey = missing.join(',');
    useEffect(() => {
        if (!base || !missingKey) return;
        api<{ items: Item[] }>(
            `${base}?field=${encodeURIComponent(path)}&ids=${missingKey}`,
        )
            .then((r) =>
                setLabels((l) => ({
                    ...l,
                    ...Object.fromEntries(r.items.map((i) => [i.id, i])),
                })),
            )
            .catch(() => setFailed(true));
    }, [base, path, missingKey]);

    useEffect(() => {
        if (!open || !base) return;
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            api<{ items: Item[] }>(
                `${base}?field=${encodeURIComponent(path)}&q=${encodeURIComponent(query)}`,
            )
                .then((r) => {
                    setResults(r.items);
                    setLabels((l) => ({
                        ...l,
                        ...Object.fromEntries(r.items.map((i) => [i.id, i])),
                    }));
                })
                .catch(() => setFailed(true));
        }, 200);

        return () => clearTimeout(timer.current);
    }, [open, query, base, path]);

    if (!base) {
        return (
            <p className="text-sm text-muted-foreground">
                Zapisz grupę pól, aby wybierać powiązane pozycje.
            </p>
        );
    }

    const set = (next: number[]) =>
        onChange(multiple ? next : (next[0] ?? null));
    const add = (id: number) => {
        if (ids.includes(id)) return;
        set(multiple ? [...ids, id].slice(0, max) : [id]);
        if (!multiple) setOpen(false);
    };
    const move = (i: number, dir: -1 | 1) => {
        const next = [...ids];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        set(next);
    };

    return (
        <div className="space-y-2">
            {ids.length > 0 && (
                <ul className="space-y-1">
                    {ids.map((id, i) => (
                        <li
                            key={id}
                            className="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5 text-sm"
                        >
                            <span className="min-w-0 flex-1 truncate">
                                {labels[id]?.label ?? `#${id}`}
                                {labels[id]?.meta && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {labels[id].meta}
                                    </span>
                                )}
                            </span>
                            {multiple && ids.length > 1 && (
                                <>
                                    <button
                                        type="button"
                                        aria-label="W górę"
                                        disabled={i === 0}
                                        onClick={() => move(i, -1)}
                                        className="disabled:opacity-30"
                                    >
                                        <ChevronUp className="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label="W dół"
                                        disabled={i === ids.length - 1}
                                        onClick={() => move(i, 1)}
                                        className="disabled:opacity-30"
                                    >
                                        <ChevronDown className="size-4" />
                                    </button>
                                </>
                            )}
                            <button
                                type="button"
                                aria-label="Usuń"
                                onClick={() => set(ids.filter((x) => x !== id))}
                            >
                                <X className="size-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {(multiple ? ids.length < max : true) && (
                <div className="relative">
                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        className="pl-8"
                        value={query}
                        placeholder={
                            !multiple && ids.length
                                ? 'Zmień wybór…'
                                : 'Szukaj i dodaj…'
                        }
                        onFocus={() => setOpen(true)}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setOpen(true);
                        }}
                        onBlur={() => setTimeout(() => setOpen(false), 150)}
                    />
                    {open && (
                        <ul className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md">
                            {results.filter((r) => !ids.includes(r.id))
                                .length === 0 && (
                                <li className="px-3 py-2 text-sm text-muted-foreground">
                                    {failed
                                        ? 'Nie udało się pobrać opcji.'
                                        : 'Brak wyników.'}
                                </li>
                            )}
                            {results
                                .filter((r) => !ids.includes(r.id))
                                .map((r) => (
                                    <li key={r.id}>
                                        <button
                                            type="button"
                                            onMouseDown={(e) =>
                                                e.preventDefault()
                                            }
                                            onClick={() => add(r.id)}
                                            className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                                        >
                                            <span className="truncate">
                                                {r.label}
                                            </span>
                                            {r.meta && (
                                                <span className="text-xs text-muted-foreground">
                                                    {r.meta}
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
