import {
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Copy,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toDraft } from '@/components/cms/field-builder/draft';
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

const categoryLabels: Record<string, string> = {
    basic: 'Podstawowe',
    content: 'Treść',
    choice: 'Wybór',
    relational: 'Relacje',
    advanced: 'Zaawansowane',
    layout: 'Układ',
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
    const isContainer = field.type === 'repeater';
    const subs = (
        Array.isArray(field.sub_fields) ? field.sub_fields : []
    ) as FieldDraft[];
    const setSubs = (next: FieldDraft[]) => onChange({ sub_fields: next });
    const moveSub = (i: number, dir: -1 | 1) => {
        const next = [...subs];
        [next[i], next[i + dir]] = [next[i + dir], next[i]];
        setSubs(next);
    };

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
                        {field.label || 'Nowe pole'}
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
                    aria-label="W górę"
                    disabled={index === 0}
                    onClick={() => onMove(-1)}
                >
                    <ChevronUp />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="W dół"
                    disabled={index === total - 1}
                    onClick={() => onMove(1)}
                >
                    <ChevronDown />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Duplikuj pole"
                    onClick={onDuplicate}
                >
                    <Copy />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Usuń pole"
                    className="text-destructive"
                    onClick={onRemove}
                >
                    <Trash2 />
                </Button>
            </div>

            {open && (
                <div className="space-y-5 border-t p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Row label="Etykieta">
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
                                label="Nazwa pola"
                                hint="Używana w szablonach i API: małe litery, cyfry, podkreślenia."
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
                        <Row label="Typ pola">
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
                                            .filter((t) => t.category === c)
                                            .map((t) => (
                                                <option
                                                    key={t.name}
                                                    value={t.name}
                                                >
                                                    {t.label}
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
                        <Row label="Instrukcja dla redaktora">
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
                            label="Pole wymagane"
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
                            label="Szerokość"
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
                            label="Klasa CSS"
                            value={field.wrapper?.class}
                            onChange={(v) =>
                                onChange({
                                    wrapper: { ...field.wrapper, class: v },
                                })
                            }
                        />
                        <TextSetting
                            label="ID elementu"
                            value={field.wrapper?.id}
                            onChange={(v) =>
                                onChange({
                                    wrapper: { ...field.wrapper, id: v },
                                })
                            }
                        />
                    </div>

                    {isContainer && (
                        <div className="space-y-3 rounded-lg border border-dashed p-3">
                            <p className="text-sm font-medium">
                                Pola w wierszu
                            </p>
                            {err('sub_fields') && (
                                <p className="text-xs text-destructive">
                                    {err('sub_fields')}
                                </p>
                            )}
                            {subs.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Dodaj pola, które będzie zawierał każdy
                                    wiersz.
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
                                            ? types.filter(
                                                  (t) => t.name !== 'repeater',
                                              )
                                            : types
                                    }
                                    siblings={subs}
                                    errors={errors}
                                    errorPrefix={`${errorPrefix}.sub_fields.${i}`}
                                    depth={depth + 1}
                                    onChange={(p) =>
                                        setSubs(
                                            subs.map((x) =>
                                                x._id !== sub._id
                                                    ? x
                                                    : '__replace' in p
                                                      ? {
                                                            ...(p.__replace as FieldDraft),
                                                        }
                                                      : ({
                                                            ...x,
                                                            ...p,
                                                        } as FieldDraft),
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
                                                name: sub.name
                                                    ? `${sub.name}_kopia`
                                                    : '',
                                                label: sub.label
                                                    ? `${sub.label} (kopia)`
                                                    : '',
                                            }),
                                        );
                                        setSubs(next);
                                    }}
                                    onRemove={() =>
                                        setSubs(
                                            subs.filter(
                                                (x) => x._id !== sub._id,
                                            ),
                                        )
                                    }
                                />
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const base =
                                        types.find((t) => t.name === 'text') ??
                                        types[0];
                                    setSubs([
                                        ...subs,
                                        toDraft({
                                            type: base.name,
                                            ...base.settings,
                                        }),
                                    ]);
                                }}
                            >
                                <Plus /> Dodaj pole podrzędne
                            </Button>
                        </div>
                    )}

                    <div className="space-y-2">
                        <p className="text-sm font-medium">Logika warunkowa</p>
                        <p className="text-xs text-muted-foreground">
                            Pole jest widoczne, gdy spełniona jest co najmniej
                            jedna grupa warunków.
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
