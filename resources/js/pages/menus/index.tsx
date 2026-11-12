import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronRight,
    GripVertical,
    Loader2,
    Plus,
    Trash2,
    IndentIncrease,
    IndentDecrease,
} from 'lucide-react';
import { useState } from 'react';
import type { DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

type ItemType = 'content' | 'term' | 'custom' | 'heading';

type Item = {
    key: string;
    depth: number;
    type: ItemType;
    label: string;
    content_id: number | null;
    term_id: number | null;
    url: string;
    new_tab: boolean;
    mega: boolean;
    mega_layout: string;
    mega_part_id: number | null;
};

type Source = { id: number; title: string; group?: string };

type Props = {
    menus: {
        id: number;
        name: string;
        slug: string;
        location: string | null;
    }[];
    menu: {
        id: number;
        name: string;
        slug: string;
        location: string | null;
        items: Item[];
    } | null;
    locations: { key: string; label: string }[];
    sources: { contents: Source[]; terms: Source[]; parts: Source[] };
};

const MAX_DEPTH = 3;

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

// Koniec poddrzewa pozycji i (wcięcie = głębokość w płaskiej liście).
const subtreeEnd = (items: Item[], i: number) => {
    let j = i + 1;
    while (j < items.length && items[j].depth > items[i].depth) j++;
    return j;
};

const shift = (block: Item[], by: number) =>
    block.map((x) => ({ ...x, depth: x.depth + by }));

function moveUp(items: Item[], i: number): Item[] {
    let k = i - 1;
    while (k >= 0 && items[k].depth > items[i].depth) k--;
    if (k < 0 || items[k].depth !== items[i].depth) return items;
    const end = subtreeEnd(items, i);
    return [
        ...items.slice(0, k),
        ...items.slice(i, end),
        ...items.slice(k, i),
        ...items.slice(end),
    ];
}

function moveDown(items: Item[], i: number): Item[] {
    const end = subtreeEnd(items, i);
    if (end >= items.length || items[end].depth !== items[i].depth)
        return items;
    const nextEnd = subtreeEnd(items, end);
    return [
        ...items.slice(0, i),
        ...items.slice(end, nextEnd),
        ...items.slice(i, end),
        ...items.slice(nextEnd),
    ];
}

// Wcięcie: pozycja (z potomkami) staje się dzieckiem poprzedniego rodzeństwa.
function indent(items: Item[], i: number): Item[] {
    const end = subtreeEnd(items, i);
    const block = items.slice(i, end);
    const maxDepth = Math.max(...block.map((x) => x.depth));
    if (i === 0 || items[i - 1].depth < items[i].depth || maxDepth >= MAX_DEPTH)
        return items;
    return [...items.slice(0, i), ...shift(block, 1), ...items.slice(end)];
}

// Wyprowadzenie: pozycja ląduje tuż za poddrzewem swojego rodzica, o poziom wyżej.
function outdent(items: Item[], i: number): Item[] {
    if (items[i].depth === 0) return items;
    const end = subtreeEnd(items, i);
    const block = items.slice(i, end);
    const rest = [...items.slice(0, i), ...items.slice(end)];
    let p = i - 1;
    while (p >= 0 && rest[p].depth >= items[i].depth) p--;
    const parentEnd = subtreeEnd(rest, p);
    return [
        ...rest.slice(0, parentEnd),
        ...shift(block, -1),
        ...rest.slice(parentEnd),
    ];
}

type DropZone = 'before' | 'after' | 'child';

/**
 * Przeciąganie: pozycja z potomkami ląduje przed celem, za jego poddrzewem albo jako jego ostatnie dziecko.
 * Zwraca tę samą tablicę, gdy upuszczenie jest niedozwolone (na siebie, w swoje poddrzewo, zbyt głęboko).
 */
function dropItem(
    items: Item[],
    from: number,
    to: number,
    zone: DropZone,
): Item[] {
    const end = subtreeEnd(items, from);
    if (to >= from && to < end) return items;
    const block = items.slice(from, end);
    const rest = [...items.slice(0, from), ...items.slice(end)];
    const target = rest[to >= end ? to - block.length : to];
    const at = rest.indexOf(target);
    const depth = zone === 'child' ? target.depth + 1 : target.depth;
    const moved = shift(block, depth - block[0].depth);
    if (Math.max(...moved.map((x) => x.depth)) > MAX_DEPTH) return items;
    const insertAt = zone === 'before' ? at : subtreeEnd(rest, at);
    return [...rest.slice(0, insertAt), ...moved, ...rest.slice(insertAt)];
}

export default function MenusIndex({ menus, menu, locations, sources }: Props) {
    const [newName, setNewName] = useState('');

    const create = () => {
        if (newName.trim())
            router.post(
                cmsUrl.menus,
                { name: newName.trim() },
                { onSuccess: () => setNewName('') },
            );
    };

    return (
        <>
            <Head title={t('Menu')} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Menu')}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {t(
                            'Menu może mieć kilka poziomów. Pozycja najwyższego poziomu może otworzyć megamenu: jej pozycje podrzędne stają się kolumnami. Wstaw menu w edytorze blokiem „Menu” albo shortcodem [menu].',
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    {menus.length > 0 && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-select">
                                {t('Wybierz menu')}
                            </Label>
                            <select
                                id="menu-select"
                                className={`${nativeClass} min-w-56`}
                                value={menu?.id ?? ''}
                                onChange={(e) =>
                                    router.get(
                                        cmsUrl.menuShow(Number(e.target.value)),
                                    )
                                }
                            >
                                {menus.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <form
                        className="flex items-end gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            create();
                        }}
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-new">{t('Nowe menu')}</Label>
                            <Input
                                id="menu-new"
                                value={newName}
                                onChange={(e) => setNewName(e.target.value)}
                                placeholder={t('Nazwa menu')}
                                maxLength={100}
                            />
                        </div>
                        <Button type="submit" disabled={!newName.trim()}>
                            <Plus />
                            {t('Utwórz')}
                        </Button>
                    </form>
                </div>

                {menu ? (
                    <MenuEditor
                        key={menu.id}
                        menu={menu}
                        locations={locations}
                        menus={menus}
                        sources={sources}
                    />
                ) : (
                    <p className="py-10 text-center text-sm text-muted-foreground">
                        {t('Nie masz jeszcze żadnego menu. Utwórz pierwsze.')}
                    </p>
                )}
            </div>
        </>
    );
}

function MenuEditor({
    menu,
    locations,
    menus,
    sources,
}: Pick<Props, 'locations' | 'sources' | 'menus'> & {
    menu: NonNullable<Props['menu']>;
}) {
    const [name, setName] = useState(menu.name);
    const [location, setLocation] = useState(menu.location ?? '');
    const [items, setItems] = useState<Item[]>(menu.items);
    const [open, setOpen] = useState<Record<string, boolean>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [dragFrom, setDragFrom] = useState<number | null>(null);
    const [drop, setDrop] = useState<{ i: number; zone: DropZone } | null>(
        null,
    );

    const endDrag = () => {
        setDragFrom(null);
        setDrop(null);
    };

    // Strefa upuszczenia zależy od wysokości kursora nad kartą: góra, dół albo środek (jako dziecko).
    const zoneAt = (e: DragEvent<HTMLElement>): DropZone => {
        const r = e.currentTarget.getBoundingClientRect();
        const y = (e.clientY - r.top) / r.height;

        return y < 0.3 ? 'before' : y > 0.7 ? 'after' : 'child';
    };

    const patch = (i: number, data: Partial<Item>) =>
        setItems((cur) => cur.map((x, n) => (n === i ? { ...x, ...data } : x)));

    const add = (type: ItemType) => {
        const key = `n${Date.now()}${items.length}`;
        setItems((cur) => [
            ...cur,
            {
                key,
                depth: 0,
                type,
                label: '',
                content_id: null,
                term_id: null,
                url: '',
                new_tab: false,
                mega: false,
                mega_layout: 'container',
                mega_part_id: null,
            },
        ]);
        setOpen((o) => ({ ...o, [key]: true }));
    };

    const remove = (i: number) => {
        // Usunięcie pozycji usuwa też jej pozycje podrzędne.
        const end = subtreeEnd(items, i);
        setItems((cur) => [...cur.slice(0, i), ...cur.slice(end)]);
    };

    const save = () => {
        setSaving(true);
        router.put(
            cmsUrl.menu(menu.id),
            {
                name,
                location: location || null,
                items: items.map((x) => ({
                    type: x.type,
                    depth: x.depth,
                    label: x.label,
                    content_id: x.content_id,
                    term_id: x.term_id,
                    url: x.url,
                    new_tab: x.new_tab,
                    mega: x.depth === 0 && x.mega,
                    mega_layout: x.mega_layout,
                    mega_part_id: x.mega_part_id,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setErrors({}),
                onError: (e) => setErrors(e as Record<string, string>),
                onFinish: () => setSaving(false),
            },
        );
    };

    const destroy = () => {
        if (confirm(t('Usunąć menu „{name}”?', { name: menu.name })))
            router.delete(cmsUrl.menu(menu.id));
    };

    const sourceTitle = (x: Item) => {
        const list = x.type === 'content' ? sources.contents : sources.terms;
        const id = x.type === 'content' ? x.content_id : x.term_id;
        return list.find((s) => s.id === id)?.title ?? '';
    };

    const groups = (list: Source[]) => {
        const map = new Map<string, Source[]>();
        list.forEach((s) => {
            const g = s.group ?? '';
            map.set(g, [...(map.get(g) ?? []), s]);
        });
        return [...map.entries()];
    };

    const typeLabel: Record<ItemType, string> = {
        content: t('Treść'),
        term: t('Kategoria lub tag'),
        custom: t('Własny odnośnik'),
        heading: t('Nagłówek bez odnośnika'),
    };

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="space-y-3">
                {items.length === 0 && (
                    <p className="rounded-md border border-dashed py-10 text-center text-sm text-muted-foreground">
                        {t('Menu jest puste. Dodaj pozycje przyciskami obok.')}
                    </p>
                )}
                {items.map((x, i) => {
                    const isOpen = !!open[x.key];
                    const err = (f: string) => errors[`items.${i}.${f}`];
                    const hasChildren =
                        i + 1 < items.length && items[i + 1].depth > x.depth;

                    return (
                        <Card
                            key={x.key}
                            data-menu-item
                            className={cn(
                                'relative gap-0 py-0',
                                dragFrom === i && 'opacity-40',
                                drop?.i === i &&
                                    drop.zone === 'child' &&
                                    'ring-2 ring-brand',
                            )}
                            style={{ marginLeft: `${x.depth * 1.5}rem` }}
                            onDragOver={(e) => {
                                if (dragFrom === null) return;
                                const zone = zoneAt(e);
                                if (
                                    dropItem(items, dragFrom, i, zone) === items
                                ) {
                                    if (drop) setDrop(null);
                                    return;
                                }
                                e.preventDefault();
                                e.dataTransfer.dropEffect = 'move';
                                if (drop?.i !== i || drop.zone !== zone)
                                    setDrop({ i, zone });
                            }}
                            onDrop={(e) => {
                                if (dragFrom === null) return;
                                e.preventDefault();
                                const next = dropItem(
                                    items,
                                    dragFrom,
                                    i,
                                    zoneAt(e),
                                );
                                if (next !== items) {
                                    setItems(next);
                                    setErrors({});
                                }
                                endDrag();
                            }}
                        >
                            {drop?.i === i && drop.zone !== 'child' && (
                                <div
                                    aria-hidden
                                    className={cn(
                                        'pointer-events-none absolute inset-x-0 h-0.5 rounded bg-brand',
                                        drop.zone === 'before'
                                            ? '-top-2'
                                            : '-bottom-2',
                                    )}
                                />
                            )}
                            <div className="flex flex-wrap items-center gap-2 p-2">
                                <span
                                    draggable
                                    role="button"
                                    tabIndex={-1}
                                    aria-label={t(
                                        'Przeciągnij, aby zmienić kolejność',
                                    )}
                                    title={t(
                                        'Przeciągnij, aby zmienić kolejność',
                                    )}
                                    className="cursor-grab touch-none text-muted-foreground hover:text-foreground active:cursor-grabbing"
                                    onDragStart={(e) => {
                                        const card =
                                            e.currentTarget.closest(
                                                '[data-menu-item]',
                                            );
                                        e.dataTransfer.effectAllowed = 'move';
                                        e.dataTransfer.setData(
                                            'text/plain',
                                            x.key,
                                        );
                                        if (card)
                                            e.dataTransfer.setDragImage(
                                                card,
                                                16,
                                                16,
                                            );
                                        setDragFrom(i);
                                    }}
                                    onDragEnd={endDrag}
                                >
                                    <GripVertical className="size-4" />
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-expanded={isOpen}
                                    aria-label={t('Szczegóły pozycji')}
                                    onClick={() =>
                                        setOpen((o) => ({
                                            ...o,
                                            [x.key]: !isOpen,
                                        }))
                                    }
                                >
                                    {isOpen ? (
                                        <ChevronDown />
                                    ) : (
                                        <ChevronRight />
                                    )}
                                </Button>
                                <Input
                                    className="min-w-40 flex-1"
                                    value={x.label}
                                    onChange={(e) =>
                                        patch(i, { label: e.target.value })
                                    }
                                    placeholder={
                                        sourceTitle(x) || typeLabel[x.type]
                                    }
                                    aria-label={t('Tekst pozycji')}
                                    maxLength={150}
                                />
                                <span className="hidden text-xs text-muted-foreground sm:inline">
                                    {x.depth === 0 && x.mega
                                        ? t('Megamenu')
                                        : typeLabel[x.type]}
                                </span>
                                <div className="flex">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Przesuń w górę')}
                                        onClick={() =>
                                            setItems(moveUp(items, i))
                                        }
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Przesuń w dół')}
                                        onClick={() =>
                                            setItems(moveDown(items, i))
                                        }
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Zmniejsz poziom')}
                                        disabled={x.depth === 0}
                                        onClick={() =>
                                            setItems(outdent(items, i))
                                        }
                                    >
                                        <IndentDecrease />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Zwiększ poziom')}
                                        onClick={() =>
                                            setItems(indent(items, i))
                                        }
                                    >
                                        <IndentIncrease />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Usuń')}
                                        onClick={() => remove(i)}
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </div>
                            {(err('label') ||
                                err('depth') ||
                                err('content_id') ||
                                err('term_id') ||
                                err('url')) &&
                                !isOpen && (
                                    <p className="px-3 pb-2 text-xs text-destructive">
                                        {err('label') ||
                                            err('depth') ||
                                            err('content_id') ||
                                            err('term_id') ||
                                            err('url')}
                                    </p>
                                )}
                            {isOpen && (
                                <div className="grid gap-3 border-t p-3">
                                    {x.type === 'content' && (
                                        <div className="grid gap-1.5">
                                            <Label>{t('Treść')}</Label>
                                            <select
                                                className={nativeClass}
                                                value={x.content_id ?? ''}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        content_id: e.target
                                                            .value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    {t('Wybierz…')}
                                                </option>
                                                {groups(sources.contents).map(
                                                    ([g, list]) => (
                                                        <optgroup
                                                            key={g}
                                                            label={g}
                                                        >
                                                            {list.map((s) => (
                                                                <option
                                                                    key={s.id}
                                                                    value={s.id}
                                                                >
                                                                    {s.title}
                                                                </option>
                                                            ))}
                                                        </optgroup>
                                                    ),
                                                )}
                                            </select>
                                            {err('content_id') && (
                                                <p className="text-xs text-destructive">
                                                    {err('content_id')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    {x.type === 'term' && (
                                        <div className="grid gap-1.5">
                                            <Label>
                                                {t('Kategoria lub tag')}
                                            </Label>
                                            <select
                                                className={nativeClass}
                                                value={x.term_id ?? ''}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        term_id: e.target.value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    {t('Wybierz…')}
                                                </option>
                                                {groups(sources.terms).map(
                                                    ([g, list]) => (
                                                        <optgroup
                                                            key={g}
                                                            label={g}
                                                        >
                                                            {list.map((s) => (
                                                                <option
                                                                    key={s.id}
                                                                    value={s.id}
                                                                >
                                                                    {s.title}
                                                                </option>
                                                            ))}
                                                        </optgroup>
                                                    ),
                                                )}
                                            </select>
                                            {err('term_id') && (
                                                <p className="text-xs text-destructive">
                                                    {err('term_id')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    {x.type === 'custom' && (
                                        <div className="grid gap-1.5">
                                            <Label>{t('Adres')}</Label>
                                            <Input
                                                value={x.url}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        url: e.target.value,
                                                    })
                                                }
                                                placeholder="https://, /kontakt, #sekcja"
                                                maxLength={500}
                                            />
                                            {err('url') && (
                                                <p className="text-xs text-destructive">
                                                    {err('url')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    {err('label') && (
                                        <p className="text-xs text-destructive">
                                            {err('label')}
                                        </p>
                                    )}
                                    {err('depth') && (
                                        <p className="text-xs text-destructive">
                                            {err('depth')}
                                        </p>
                                    )}
                                    {x.type !== 'heading' && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={x.new_tab}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        new_tab:
                                                            e.target.checked,
                                                    })
                                                }
                                            />
                                            {t('Otwórz w nowej karcie')}
                                        </label>
                                    )}
                                    {x.depth === 0 && (
                                        <div className="grid gap-3 rounded-md bg-muted/40 p-3">
                                            <label className="flex items-center gap-2 text-sm font-medium">
                                                <input
                                                    type="checkbox"
                                                    checked={x.mega}
                                                    onChange={(e) =>
                                                        patch(i, {
                                                            mega: e.target
                                                                .checked,
                                                        })
                                                    }
                                                />
                                                {t('Megamenu')}
                                            </label>
                                            {x.mega && (
                                                <>
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'Pozycje podrzędne tej pozycji staną się kolumnami, a ich pozycje podrzędne odnośnikami w kolumnie.',
                                                        )}
                                                        {!hasChildren &&
                                                            ` ${t('Dodaj pozycje podrzędne przyciskiem „Zwiększ poziom”.')}`}
                                                    </p>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="grid gap-1.5">
                                                            <Label>
                                                                {t('Szerokość')}
                                                            </Label>
                                                            <select
                                                                className={
                                                                    nativeClass
                                                                }
                                                                value={
                                                                    x.mega_layout
                                                                }
                                                                onChange={(e) =>
                                                                    patch(i, {
                                                                        mega_layout:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    })
                                                                }
                                                            >
                                                                <option value="container">
                                                                    {t(
                                                                        'Szerokość kontenera',
                                                                    )}
                                                                </option>
                                                                <option value="full">
                                                                    {t(
                                                                        'Pełna szerokość',
                                                                    )}
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <div className="grid gap-1.5">
                                                            <Label>
                                                                {t(
                                                                    'Dodatkowy panel (część)',
                                                                )}
                                                            </Label>
                                                            <select
                                                                className={
                                                                    nativeClass
                                                                }
                                                                value={
                                                                    x.mega_part_id ??
                                                                    ''
                                                                }
                                                                onChange={(e) =>
                                                                    patch(i, {
                                                                        mega_part_id:
                                                                            e
                                                                                .target
                                                                                .value
                                                                                ? Number(
                                                                                      e
                                                                                          .target
                                                                                          .value,
                                                                                  )
                                                                                : null,
                                                                    })
                                                                }
                                                            >
                                                                <option value="">
                                                                    {t('Brak')}
                                                                </option>
                                                                {sources.parts.map(
                                                                    (p) => (
                                                                        <option
                                                                            key={
                                                                                p.id
                                                                            }
                                                                            value={
                                                                                p.id
                                                                            }
                                                                        >
                                                                            {
                                                                                p.title
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                        </Card>
                    );
                })}
            </div>

            <div className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Dodaj pozycję')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {(
                            [
                                'content',
                                'term',
                                'custom',
                                'heading',
                            ] as ItemType[]
                        ).map((tp) => (
                            <Button
                                key={tp}
                                type="button"
                                variant="outline"
                                className="justify-start"
                                onClick={() => add(tp)}
                            >
                                <Plus />
                                {typeLabel[tp]}
                            </Button>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Ustawienia menu')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-name">{t('Nazwa')}</Label>
                            <Input
                                id="menu-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                maxLength={100}
                            />
                            {errors.name && (
                                <p className="text-xs text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-location">
                                {t('Lokalizacja w motywie')}
                            </Label>
                            <select
                                id="menu-location"
                                className={nativeClass}
                                value={location}
                                onChange={(e) => setLocation(e.target.value)}
                            >
                                <option value="">{t('Brak')}</option>
                                {locations.map((l) => {
                                    const taken = menus.find(
                                        (m) =>
                                            m.location === l.key &&
                                            m.id !== menu.id,
                                    );
                                    return (
                                        <option key={l.key} value={l.key}>
                                            {t(l.label)}
                                            {taken ? ` (${taken.name})` : ''}
                                        </option>
                                    );
                                })}
                            </select>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('Shortcode')}:{' '}
                            <code>[menu name="{menu.slug}"]</code>
                        </p>
                        <Button type="button" onClick={save} disabled={saving}>
                            {saving && <Loader2 className="animate-spin" />}
                            {t('Zapisz menu')}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive"
                            onClick={destroy}
                        >
                            <Trash2 />
                            {t('Usuń menu')}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
