import { File as FileIcon, ImagePlus, X } from 'lucide-react';
import { useState } from 'react';
import { FlexibleControl } from '@/components/cms/flexible-control';
import { GroupControl } from '@/components/cms/group-control';
import { MapControl } from '@/components/cms/map-control';
import { MediaPicker } from '@/components/cms/media-picker';
import { RelationControl } from '@/components/cms/relation-control';
import { RepeaterControl } from '@/components/cms/repeater-control';
import { WysiwygEditor } from '@/components/cms/wysiwyg-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { isVisible } from '@/lib/conditional-logic';
import { cn } from '@/lib/utils';
import type { FieldDefinition, MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

type Values = Record<string, unknown>;
type MediaMap = Record<number, MediaItem>;

type ControlProps = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
    media: MediaMap;
    rememberMedia: (items: MediaItem[]) => void;
    id: string;
    errors: Record<string, string | undefined>;
    /** Pełny klucz błędów tego pola, np. "fields.slajdy" albo "fields.slajdy.0.tytul". */
    errorKey: string;
    groupId?: number;
    /** Ścieżka nazw pola w grupie (bez indeksów wierszy), np. "slajdy.powiazany". */
    path: string;
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const s = (field: FieldDefinition, key: string): string =>
    field[key] === undefined || field[key] === null ? '' : String(field[key]);

/** Opcje wyboru: obiekt {wartość: etykieta} albo lista. */
function choicesOf(field: FieldDefinition): [string, string][] {
    const raw = field.choices;
    if (Array.isArray(raw)) return raw.map((v) => [String(v), String(v)]);
    if (raw && typeof raw === 'object')
        return Object.entries(raw as Record<string, string>).map(([k, v]) => [
            k,
            String(v),
        ]);

    return [];
}

const toList = (v: unknown): string[] =>
    Array.isArray(v)
        ? v.map(String)
        : v === null || v === undefined || v === ''
          ? []
          : [String(v)];

// --- Konwersje formatów pól daty (serwer zapisuje Y-m-d H:i:s oraz H:i:s) ---
const dtToInput = (v: unknown) =>
    typeof v === 'string' && v ? v.replace(' ', 'T').slice(0, 16) : '';
const dtFromInput = (v: string) => (v ? `${v.replace('T', ' ')}:00` : '');
const timeToInput = (v: unknown) =>
    typeof v === 'string' ? v.slice(0, 5) : '';
const timeFromInput = (v: string) => (v ? `${v}:00` : '');

function MediaControl({
    field,
    value,
    onChange,
    media,
    rememberMedia,
}: ControlProps) {
    const [open, setOpen] = useState(false);
    const isGallery = field.type === 'gallery';
    const ids: number[] = isGallery
        ? Array.isArray(value)
            ? (value as number[])
            : []
        : value
          ? [Number(value)]
          : [];
    const items = ids.map((id) => media[id]).filter(Boolean);
    const max = Number(field.max) || Infinity;

    const pick = (picked: MediaItem[]) => {
        rememberMedia(picked);
        if (isGallery) {
            onChange(
                [...new Set([...ids, ...picked.map((p) => p.id)])].slice(
                    0,
                    max,
                ),
            );
        } else {
            onChange(picked[0]?.id ?? null);
        }
    };

    const move = (index: number, dir: -1 | 1) => {
        const next = [...ids];
        const target = index + dir;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <div className="space-y-2">
            {items.length > 0 && (
                <div
                    className={cn(
                        'grid gap-2',
                        isGallery
                            ? 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-5'
                            : 'grid-cols-1 sm:max-w-xs',
                    )}
                >
                    {items.map((item, index) => (
                        <div
                            key={item.id}
                            className="group relative overflow-hidden rounded-lg border bg-muted"
                        >
                            {item.mime.startsWith('image/') ? (
                                <img
                                    src={item.sizes.thumbnail?.url ?? item.url}
                                    alt={item.alt}
                                    className="aspect-square w-full object-cover"
                                />
                            ) : (
                                <div className="flex aspect-square flex-col items-center justify-center gap-1 p-2 text-muted-foreground">
                                    <FileIcon className="size-8" />
                                    <span className="line-clamp-2 text-center text-xs break-all">
                                        {item.filename}
                                    </span>
                                </div>
                            )}
                            <button
                                type="button"
                                aria-label={t('Usuń')}
                                onClick={() =>
                                    onChange(
                                        isGallery
                                            ? ids.filter((id) => id !== item.id)
                                            : null,
                                    )
                                }
                                className="absolute top-1 right-1 rounded-full bg-black/70 p-1 text-white opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                            >
                                <X className="size-3.5" />
                            </button>
                            {isGallery && ids.length > 1 && (
                                <div className="absolute bottom-1 left-1 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        type="button"
                                        aria-label={t('Przesuń wcześniej')}
                                        onClick={() => move(index, -1)}
                                        className="rounded bg-black/70 px-1.5 text-xs text-white"
                                    >
                                        ‹
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={t('Przesuń później')}
                                        onClick={() => move(index, 1)}
                                        className="rounded bg-black/70 px-1.5 text-xs text-white"
                                    >
                                        ›
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
            {(isGallery ? ids.length < max : items.length === 0) && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                >
                    <ImagePlus />{' '}
                    {isGallery
                        ? t('Dodaj obrazy')
                        : field.type === 'image'
                          ? t('Wybierz obraz')
                          : t('Wybierz plik')}
                </Button>
            )}
            <MediaPicker
                open={open}
                onOpenChange={setOpen}
                multiple={isGallery}
                imagesOnly={field.type !== 'file'}
                onPick={pick}
            />
        </div>
    );
}

function Control(props: ControlProps) {
    const { field, value, onChange, id } = props;
    const text = value === null || value === undefined ? '' : String(value);

    switch (field.type) {
        case 'text':
        case 'email':
        case 'url':
        case 'password':
            return (
                <Input
                    id={id}
                    type={field.type === 'text' ? 'text' : field.type}
                    value={text}
                    placeholder={s(field, 'placeholder')}
                    maxLength={Number(field.maxlength) || undefined}
                    autoComplete={
                        field.type === 'password' ? 'new-password' : undefined
                    }
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'textarea':
            return (
                <textarea
                    id={id}
                    rows={Number(field.rows) || 4}
                    value={text}
                    placeholder={s(field, 'placeholder')}
                    maxLength={Number(field.maxlength) || undefined}
                    onChange={(e) => onChange(e.target.value)}
                    className={cn(nativeClass, 'h-auto py-2')}
                />
            );
        case 'number':
            return (
                <Input
                    id={id}
                    type="number"
                    value={text}
                    min={s(field, 'min') || undefined}
                    max={s(field, 'max') || undefined}
                    step={s(field, 'step') || 'any'}
                    placeholder={s(field, 'placeholder')}
                    onChange={(e) =>
                        onChange(e.target.value === '' ? null : e.target.value)
                    }
                />
            );
        case 'range':
            return (
                <div className="flex items-center gap-3">
                    <input
                        id={id}
                        type="range"
                        className="flex-1 accent-brand"
                        value={
                            text ||
                            s(field, 'default_value') ||
                            s(field, 'min') ||
                            0
                        }
                        min={s(field, 'min') || 0}
                        max={s(field, 'max') || 100}
                        step={s(field, 'step') || 1}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    <span className="w-10 text-right text-sm tabular-nums">
                        {text ||
                            s(field, 'default_value') ||
                            s(field, 'min') ||
                            0}
                    </span>
                </div>
            );
        case 'select': {
            const multiple = Boolean(field.multiple);
            const options = choicesOf(field);

            return (
                <select
                    id={id}
                    multiple={multiple}
                    className={cn(nativeClass, multiple && 'h-28')}
                    value={multiple ? toList(value) : text}
                    onChange={(e) =>
                        onChange(
                            multiple
                                ? Array.from(
                                      e.target.selectedOptions,
                                      (o) => o.value,
                                  )
                                : e.target.value || null,
                        )
                    }
                >
                    {!multiple && (
                        <option value="">
                            {field.allow_null ? '— brak —' : '— wybierz —'}
                        </option>
                    )}
                    {options.map(([v, label]) => (
                        <option key={v} value={v}>
                            {label}
                        </option>
                    ))}
                </select>
            );
        }
        case 'checkbox': {
            const selected = toList(value);

            return (
                <div
                    className={cn(
                        'flex gap-x-4 gap-y-2',
                        field.layout === 'horizontal'
                            ? 'flex-wrap'
                            : 'flex-col',
                    )}
                >
                    {choicesOf(field).map(([v, label]) => (
                        <label
                            key={v}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                className="size-4 accent-brand"
                                checked={selected.includes(v)}
                                onChange={(e) =>
                                    onChange(
                                        e.target.checked
                                            ? [...selected, v]
                                            : selected.filter((x) => x !== v),
                                    )
                                }
                            />
                            {label}
                        </label>
                    ))}
                </div>
            );
        }
        case 'radio':
            return (
                <div
                    className={cn(
                        'flex gap-x-4 gap-y-2',
                        field.layout === 'horizontal'
                            ? 'flex-wrap'
                            : 'flex-col',
                    )}
                >
                    {choicesOf(field).map(([v, label]) => (
                        <label
                            key={v}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="radio"
                                name={id}
                                className="size-4 accent-brand"
                                checked={text === v}
                                onChange={() => onChange(v)}
                            />
                            {label}
                        </label>
                    ))}
                </div>
            );
        case 'button_group':
            return (
                <div className="inline-flex overflow-hidden rounded-md border">
                    {choicesOf(field).map(([v, label]) => (
                        <button
                            key={v}
                            type="button"
                            onClick={() => onChange(v)}
                            className={cn(
                                'border-r px-3 py-1.5 text-sm last:border-r-0',
                                text === v
                                    ? 'bg-brand text-white'
                                    : 'hover:bg-muted',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            );
        case 'true_false': {
            const on = value === true || value === '1' || value === 1;

            return (
                <button
                    type="button"
                    role="switch"
                    aria-checked={on}
                    id={id}
                    onClick={() => onChange(!on)}
                    className="flex items-center gap-2 text-sm"
                >
                    <span
                        className={cn(
                            'relative h-5 w-9 rounded-full transition-colors',
                            on ? 'bg-brand' : 'bg-input',
                        )}
                    >
                        <span
                            className={cn(
                                'absolute top-0.5 size-4 rounded-full bg-white shadow transition-all',
                                on ? 'left-[1.1rem]' : 'left-0.5',
                            )}
                        />
                    </span>
                    {on
                        ? s(field, 'ui_on_text') || t('Tak')
                        : s(field, 'ui_off_text') || t('Nie')}
                </button>
            );
        }
        case 'color_picker':
            return (
                <div className="flex items-center gap-2">
                    <input
                        id={id}
                        type="color"
                        className="h-9 w-12 cursor-pointer rounded border bg-transparent p-1"
                        value={/^#[0-9a-f]{6}$/i.test(text) ? text : '#000000'}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    <Input
                        value={text}
                        placeholder="#000000"
                        className="w-36"
                        onChange={(e) => onChange(e.target.value)}
                    />
                </div>
            );
        case 'date_picker':
            return (
                <Input
                    id={id}
                    type="date"
                    value={text}
                    onChange={(e) => onChange(e.target.value || null)}
                />
            );
        case 'date_time_picker':
            return (
                <Input
                    id={id}
                    type="datetime-local"
                    value={dtToInput(value)}
                    onChange={(e) =>
                        onChange(dtFromInput(e.target.value) || null)
                    }
                />
            );
        case 'time_picker':
            return (
                <Input
                    id={id}
                    type="time"
                    value={timeToInput(value)}
                    onChange={(e) =>
                        onChange(timeFromInput(e.target.value) || null)
                    }
                />
            );
        case 'image':
        case 'file':
        case 'gallery':
            return <MediaControl {...props} />;
        case 'wysiwyg':
            return (
                <WysiwygEditor
                    id={id}
                    value={text}
                    onChange={onChange}
                    toolbar={field.toolbar === 'basic' ? 'basic' : 'full'}
                    minHeight={Number(field.min_height) || 200}
                />
            );
        case 'oembed':
            return (
                <Input
                    id={id}
                    type="url"
                    value={text}
                    placeholder="https://www.youtube.com/watch?v=…"
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'post_object':
        case 'relationship':
        case 'taxonomy':
        case 'user':
            return (
                <RelationControl
                    field={field}
                    value={value}
                    onChange={onChange}
                    groupId={props.groupId}
                    path={props.path}
                />
            );
        case 'repeater':
            return <RepeaterControl {...props} />;
        case 'group':
            return <GroupControl {...props} />;
        case 'flexible_content':
            return <FlexibleControl {...props} />;
        case 'map':
            return (
                <MapControl field={field} value={value} onChange={onChange} />
            );
        default:
            return (
                <p className="text-sm text-muted-foreground">
                    {t('Typ pola „{type}” nie ma jeszcze edytora w panelu.', {
                        type: field.type,
                    })}
                </p>
            );
    }
}

type FieldListProps = {
    fields: FieldDefinition[];
    values: Values;
    errors: Record<string, string | undefined>;
    media: MediaMap;
    rememberMedia: (items: MediaItem[]) => void;
    onChange: (name: string, value: unknown) => void;
    labelPlacement?: 'top' | 'left';
    /** Prefiks kluczy błędów (domyślnie "fields"; w wierszach Repeatera "fields.nazwa.indeks"). */
    errorKey?: string;
    groupId?: number;
    /** Nazwy pól nadrzędnych (Repeater), np. "slajdy". */
    path?: string;
};

/** Formularz pól grupy: etykiety, instrukcje, błędy, logika warunkowa i elementy układu. */
export function FieldList({
    fields,
    values,
    errors,
    media,
    rememberMedia,
    onChange,
    labelPlacement = 'top',
    errorKey = 'fields',
    groupId,
    path = '',
}: FieldListProps) {
    return (
        <div className="flex flex-wrap gap-x-4 gap-y-5">
            {fields.map((field, index) => {
                if (!isVisible(field, values)) return null;

                const key = `${field.name || field.type}-${index}`;

                if (field.type === 'message') {
                    return (
                        <p
                            key={key}
                            className="w-full rounded-lg bg-muted/60 p-3 text-sm text-muted-foreground"
                        >
                            {s(field, 'message')}
                        </p>
                    );
                }

                if (field.type === 'tab' || field.type === 'accordion') {
                    return field.label ? (
                        <h3
                            key={key}
                            className="w-full border-b pb-1 text-sm font-semibold"
                        >
                            {field.label}
                        </h3>
                    ) : null;
                }

                const id = `field-${field.name}`;
                const width = Number(field.wrapper?.width);
                const fieldKey = `${errorKey}.${field.name}`;
                const fieldPath = path ? `${path}.${field.name}` : field.name;
                // Dla kontenerów błędy wierszy pokazują same wiersze; tutaj tylko błąd całego pola.
                const error =
                    errors[fieldKey] ??
                    (['repeater', 'group', 'flexible_content'].includes(
                        field.type,
                    )
                        ? undefined
                        : Object.entries(errors).find(([k]) =>
                              k.startsWith(`${fieldKey}.`),
                          )?.[1]);

                return (
                    <div
                        key={key}
                        id={field.wrapper?.id || undefined}
                        className={cn(
                            'min-w-0',
                            field.wrapper?.class,
                            labelPlacement === 'left' &&
                                'sm:grid sm:grid-cols-[10rem_1fr] sm:gap-3',
                        )}
                        style={{
                            width:
                                width > 0 && width < 100
                                    ? `calc(${width}% - 1rem)`
                                    : '100%',
                        }}
                    >
                        <div className="mb-1.5 sm:mb-0">
                            <Label htmlFor={id}>
                                {field.label || field.name}
                                {field.required && (
                                    <span className="ml-0.5 text-destructive">
                                        *
                                    </span>
                                )}
                            </Label>
                            {field.instructions && (
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {field.instructions}
                                </p>
                            )}
                        </div>
                        <div>
                            <Control
                                field={field}
                                id={id}
                                value={values[field.name]}
                                media={media}
                                rememberMedia={rememberMedia}
                                errors={errors}
                                errorKey={fieldKey}
                                groupId={groupId}
                                path={fieldPath}
                                onChange={(v) => onChange(field.name, v)}
                            />
                            {error && (
                                <p className="mt-1 text-xs text-destructive">
                                    {error}
                                </p>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
