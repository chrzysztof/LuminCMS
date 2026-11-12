import {
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Copy,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toDraft, uid } from '@/components/cms/field-builder/draft';
import { ConditionalEditor } from '@/components/cms/field-builder/logic-editors';
import { fieldNameFromLabel } from '@/components/cms/field-builder/slug';
import {
    Row,
    SelectSetting,
    TextSetting,
    Toggle,
    nativeClass,
} from '@/components/cms/field-builder/setting-inputs';
import { TypeSettings } from '@/components/cms/field-builder/type-settings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FieldDraft, FieldTypeInfo } from '@/types/field-builder';
import { t } from '@/lib/i18n';

const categoryLabels: Record<string, string> = {
    basic: t('Podstawowe'),
    content: t('Treść'),
    choice: t('Wybór'),
    relational: t('Relacje'),
    advanced: t('Zaawansowane'),
    layout: t('Układ'),
};

type Props = {
    field: FieldDraft;
    index: number;
    total: number;
    types: FieldTypeInfo[];
    siblings: FieldDraft[];
    errors: Record<string, string | undefined>;
    /** Prefiks kluczy błędów tego pola, np. "fields.2" albo "fields.2.sub_fields.0". */
    errorPrefix: string;
    depth?: number;
    onChange: (patch: Record<string, unknown>) => void;
    onMove: (dir: -1 | 1) => void;
    onDuplicate: () => void;
    onRemove: () => void;
};

export function FieldEditor({
    field,
    index,
    total,
    types,
    siblings,
    errors,
    errorPrefix,
    depth = 0,
    onChange,
    onMove,
    onDuplicate,
    onRemove,
}: Props) {
    const [open, setOpen] = useState(!field.name && !field.label);
    const [nameTouched, setNameTouched] = useState(Boolean(field.name));
    const info = types.find((t) => t.name === field.type);
    const err = (key: string) => errors[`${errorPrefix}.${key}`];
    const hasError = Object.keys(errors).some((k) =>
        k.startsWith(`fields.${index}.`),
    );
    const candidates = siblings
        .filter((s) => s._id !== field._id && s.name)
        .map((s) => ({ name: s.name, label: s.label }));
    const categories = [...new Set(types.map((t) => t.category))];
    const isContainer = field.type === 'repeater' || field.type === 'group';
    const isFlexible = field.type === 'flexible_content';

    const changeType = (type: string) => {
        const next = types.find((t) => t.name === type);
        // Przy zmianie typu zostawiamy ustawienia wspólne, a ustawienia typu bierzemy z domyślnych nowego typu.
        const common = [
            '_id',
            'label',
            'name',
            'instructions',
            'required',
            'conditional_logic',
            'wrapper',
        ];
        const kept = Object.fromEntries(
            Object.entries(field).filter(([k]) => common.includes(k)),
        );
        onChange({ __replace: { ...kept, type, ...next?.settings } });
    };

    return (
        <div
            className={`rounded-lg border bg-card ${hasError ? 'border-destructive' : ''}`}
        >
            <div className="flex items-center gap-2 p-2">
                <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-2 rounded px-2 py-1 text-left hover:bg-muted"
                    onClick={() => setOpen(!open)}
                >
                    {open ? (
                        <ChevronDown className="size-4 shrink-0" />
                    ) : (
                        <ChevronRight className="size-4 shrink-0" />
                    )}
                    <span className="truncate font-medium">
                        {field.label || t('Nowe pole')}
                    </span>
                    <code className="truncate text-xs text-muted-foreground">
                        {field.name}
                    </code>
                    <span className="ml-auto shrink-0 rounded bg-muted px-2 py-0.5 text-xs">
                        {info?.label ?? field.type}
                    </span>
                </button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('W górę')}
                    disabled={index === 0}
                    onClick={() => onMove(-1)}
                >
                    <ChevronUp />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('W dół')}
                    disabled={index === total - 1}
                    onClick={() => onMove(1)}
                >
                    <ChevronDown />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('Duplikuj pole')}
                    onClick={onDuplicate}
                >
                    <Copy />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('Usuń pole')}
                    className="text-destructive"
                    onClick={onRemove}
                >
                    <Trash2 />
                </Button>
            </div>

            {open && (
                <div className="space-y-5 border-t p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Row label={t('Etykieta')}>
                            <Input
                                value={field.label}
                                onChange={(e) => {
                                    const patch: Record<string, unknown> = {
                                        label: e.target.value,
                                    };
                                    if (!nameTouched && info?.stores_data)
                                        patch.name = fieldNameFromLabel(
                                            e.target.value,
                                        );
                                    onChange(patch);
                                }}
                            />
                        </Row>
                        {info?.stores_data && (
                            <Row
                                label={t('Nazwa pola')}
                                hint={t(
                                    'Używana w szablonach i API: małe litery, cyfry, podkreślenia.',
                                )}
                            >
                                <Input
                                    value={field.name}
                                    className="font-mono"
                                    onChange={(e) => {
                                        setNameTouched(true);
                                        onChange({ name: e.target.value });
                                    }}
                                />
                                {err('name') && (
                                    <p className="text-xs text-destructive">
                                        {err('name')}
                                    </p>
                                )}
                            </Row>
                        )}
                        <Row label={t('Typ pola')}>
                            <select
                                className={nativeClass}
                                value={field.type}
                                onChange={(e) => changeType(e.target.value)}
                            >
                                {categories.map((c) => (
                                    <optgroup
                                        key={c}
                                        label={categoryLabels[c] ?? c}
                                    >
                                        {types
                                            .filter((ft) => ft.category === c)
                                            .map((ft) => (
                                                <option
                                                    key={ft.name}
                                                    value={ft.name}
                                                >
                                                    {t(ft.label)}
                                                </option>
                                            ))}
                                    </optgroup>
                                ))}
                            </select>
                            {err('type') && (
                                <p className="text-xs text-destructive">
                                    {err('type')}
                                </p>
                            )}
                        </Row>
                        <Row label={t('Instrukcja dla redaktora')}>
                            <Input
                                value={field.instructions}
                                onChange={(e) =>
                                    onChange({ instructions: e.target.value })
                                }
                            />
                        </Row>
                    </div>

                    {info?.stores_data && (
                        <Toggle
                            label={t('Pole wymagane')}
                            checked={field.required}
                            onChange={(v) => onChange({ required: v })}
                        />
                    )}

                    <TypeSettings
                        field={field}
                        defaults={info?.settings ?? {}}
                        onChange={(k, v) => onChange({ [k]: v })}
                    />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectSetting
                            label={t('Szerokość')}
                            value={field.wrapper?.width ?? ''}
                            options={[
                                ['', '100%'],
                                ['75', '75%'],
                                ['66', '66%'],
                                ['50', '50%'],
                                ['33', '33%'],
                                ['25', '25%'],
                            ]}
                            onChange={(v) =>
                                onChange({
                                    wrapper: { ...field.wrapper, width: v },
                                })
                            }
                        />
                        <TextSetting
                            label={t('Klasa CSS')}
                            value={field.wrapper?.class}
                            onChange={(v) =>
                                onChange({
                                    wrapper: { ...field.wrapper, class: v },
                                })
                            }
                        />
                        <TextSetting
                            label={t('ID elementu')}
                            value={field.wrapper?.id}
                            onChange={(v) =>
                                onChange({
                                    wrapper: { ...field.wrapper, id: v },
                                })
                            }
                        />
                    </div>

                    {isContainer && (
                        <SubFieldList
                            title={
                                field.type === 'group'
                                    ? t('Pola w grupie')
                                    : t('Pola w wierszu')
                            }
                            subs={
                                (Array.isArray(field.sub_fields)
                                    ? field.sub_fields
                                    : []) as FieldDraft[]
                            }
                            onChange={(next) => onChange({ sub_fields: next })}
                            types={types}
                            depth={depth}
                            errors={errors}
                            errorPrefix={`${errorPrefix}.sub_fields`}
                        />
                    )}

                    {isFlexible && (
                        <LayoutsEditor
                            layouts={
                                (Array.isArray(field.layouts)
                                    ? field.layouts
                                    : []) as LayoutDraft[]
                            }
                            onChange={(next) => onChange({ layouts: next })}
                            types={types}
                            depth={depth}
                            errors={errors}
                            errorPrefix={errorPrefix}
                        />
                    )}

                    <div className="space-y-2">
                        <p className="text-sm font-medium">
                            {t('Logika warunkowa')}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Pole jest widoczne, gdy spełniona jest co najmniej jedna grupa warunków.',
                            )}
                        </p>
                        {err('conditional_logic') && (
                            <p className="text-xs text-destructive">
                                {err('conditional_logic')}
                            </p>
                        )}
                        <ConditionalEditor
                            value={field.conditional_logic}
                            candidates={candidates}
                            onChange={(v) => onChange({ conditional_logic: v })}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

const NESTED = ['repeater', 'group', 'flexible_content'];

type SubFieldListProps = {
    title: string;
    subs: FieldDraft[];
    onChange: (next: FieldDraft[]) => void;
    types: FieldTypeInfo[];
    depth: number;
    errors: Record<string, string | undefined>;
    /** Prefiks kluczy błędów listy, np. "fields.1.sub_fields". */
    errorPrefix: string;
};

/** Lista pól podrzędnych kontenera (wiersza Repeatera, Group albo układu Flexible Content). */
function SubFieldList({
    title,
    subs,
    onChange: setSubs,
    types,
    depth,
    errors,
    errorPrefix,
}: SubFieldListProps) {
    const moveSub = (i: number, dir: -1 | 1) => {
        const next = [...subs];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        setSubs(next);
    };

    return (
        <div className="space-y-3 rounded-lg border border-dashed p-3">
            <p className="text-sm font-medium">{title}</p>
            {errors[errorPrefix] && (
                <p className="text-xs text-destructive">
                    {errors[errorPrefix]}
                </p>
            )}
            {subs.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('Dodaj pola, które będzie zawierał każdy wiersz.')}
                </p>
            )}
            {subs.map((sub, i) => (
                <FieldEditor
                    key={sub._id}
                    field={sub}
                    index={i}
                    total={subs.length}
                    types={
                        depth >= 2
                            ? types.filter((ft) => !NESTED.includes(ft.name))
                            : types
                    }
                    siblings={subs}
                    errors={errors}
                    errorPrefix={`${errorPrefix}.${i}`}
                    depth={depth + 1}
                    onChange={(p) =>
                        setSubs(
                            subs.map((x) =>
                                x._id !== sub._id
                                    ? x
                                    : '__replace' in p
                                      ? { ...(p.__replace as FieldDraft) }
                                      : ({ ...x, ...p } as FieldDraft),
                            ),
                        )
                    }
                    onMove={(d) => moveSub(i, d)}
                    onDuplicate={() => {
                        const next = [...subs];
                        next.splice(
                            i + 1,
                            0,
                            toDraft({
                                ...sub,
                                name: sub.name ? `${sub.name}_kopia` : '',
                                label: sub.label
                                    ? t('{name} (kopia)', { name: sub.label })
                                    : '',
                            }),
                        );
                        setSubs(next);
                    }}
                    onRemove={() =>
                        setSubs(subs.filter((x) => x._id !== sub._id))
                    }
                />
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                    const base =
                        types.find((ft) => ft.name === 'text') ?? types[0];
                    setSubs([
                        ...subs,
                        toDraft({ type: base.name, ...base.settings }),
                    ]);
                }}
            >
                <Plus /> {t('Dodaj pole podrzędne')}
            </Button>
        </div>
    );
}

type LayoutDraft = {
    _id: string;
    name: string;
    label: string;
    min: number | string;
    max: number | string;
    sub_fields: FieldDraft[];
};

type LayoutsEditorProps = {
    layouts: LayoutDraft[];
    onChange: (next: LayoutDraft[]) => void;
    types: FieldTypeInfo[];
    depth: number;
    errors: Record<string, string | undefined>;
    errorPrefix: string;
};

/** Układy pola Flexible Content: nazwa, etykieta, limity i własne pola każdego układu. */
function LayoutsEditor({
    layouts,
    onChange,
    types,
    depth,
    errors,
    errorPrefix,
}: LayoutsEditorProps) {
    const patch = (id: string, p: Partial<LayoutDraft>) =>
        onChange(layouts.map((l) => (l._id === id ? { ...l, ...p } : l)));
    const move = (i: number, dir: -1 | 1) => {
        const next = [...layouts];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        onChange(next);
    };

    return (
        <div className="space-y-3 rounded-lg border border-dashed p-3">
            <p className="text-sm font-medium">{t('Układy')}</p>
            {errors[`${errorPrefix}.layouts`] && (
                <p className="text-xs text-destructive">
                    {errors[`${errorPrefix}.layouts`]}
                </p>
            )}
            {layouts.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Dodaj układy, spośród których redaktor wybiera wiersze.',
                    )}
                </p>
            )}
            {layouts.map((layout, i) => {
                const lp = `${errorPrefix}.layouts.${i}`;

                return (
                    <div
                        key={layout._id}
                        className="space-y-3 rounded-lg border bg-card p-3"
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <Row label={t('Etykieta układu')}>
                                <Input
                                    value={layout.label}
                                    onChange={(e) =>
                                        patch(layout._id, {
                                            label: e.target.value,
                                            ...(layout.name === '' ||
                                            layout.name ===
                                                fieldNameFromLabel(layout.label)
                                                ? {
                                                      name: fieldNameFromLabel(
                                                          e.target.value,
                                                      ),
                                                  }
                                                : {}),
                                        })
                                    }
                                />
                            </Row>
                            <Row label={t('Nazwa układu')}>
                                <Input
                                    value={layout.name}
                                    className="font-mono"
                                    onChange={(e) =>
                                        patch(layout._id, {
                                            name: e.target.value,
                                        })
                                    }
                                />
                                {errors[`${lp}.name`] && (
                                    <p className="text-xs text-destructive">
                                        {errors[`${lp}.name`]}
                                    </p>
                                )}
                            </Row>
                            <TextSetting
                                label={t('Minimum')}
                                type="number"
                                value={layout.min}
                                onChange={(v) => patch(layout._id, { min: v })}
                            />
                            <TextSetting
                                label={t('Maksimum')}
                                type="number"
                                value={layout.max}
                                onChange={(v) => patch(layout._id, { max: v })}
                            />
                            <div className="ml-auto flex gap-1">
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
                                    disabled={i === layouts.length - 1}
                                    onClick={() => move(i, 1)}
                                >
                                    <ChevronDown />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Usuń układ')}
                                    className="text-destructive"
                                    onClick={() =>
                                        onChange(
                                            layouts.filter(
                                                (l) => l._id !== layout._id,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </div>
                        <SubFieldList
                            title={t('Pola układu')}
                            subs={layout.sub_fields}
                            onChange={(next) =>
                                patch(layout._id, { sub_fields: next })
                            }
                            types={types}
                            depth={depth}
                            errors={errors}
                            errorPrefix={`${lp}.sub_fields`}
                        />
                    </div>
                );
            })}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                    onChange([
                        ...layouts,
                        {
                            _id: uid(),
                            name: '',
                            label: '',
                            min: '',
                            max: '',
                            sub_fields: [],
                        },
                    ])
                }
            >
                <Plus /> {t('Dodaj układ')}
            </Button>
        </div>
    );
}
