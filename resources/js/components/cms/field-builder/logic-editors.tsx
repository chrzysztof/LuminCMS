import { Plus, Trash2 } from 'lucide-react';
import { nativeClass } from '@/components/cms/field-builder/setting-inputs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ConditionalRule } from '@/types/cms';
import type { LocationRule } from '@/types/field-builder';
import { t } from '@/lib/i18n';

const operators: [string, string][] = [
    ['==', t('jest równe')],
    ['!=', t('nie jest równe')],
    ['==empty', 'jest puste'],
    ['!=empty', 'nie jest puste'],
    ['==contains', 'zawiera'],
    ['==pattern', 'pasuje do wzorca (regex)'],
];

type ConditionalProps = {
    value: false | ConditionalRule[][];
    candidates: { name: string; label: string }[];
    onChange: (v: false | ConditionalRule[][]) => void;
};

/** Grupy reguł: między grupami „lub”, wewnątrz grupy „i”. */
export function ConditionalEditor({
    value,
    candidates,
    onChange,
}: ConditionalProps) {
    const groups = value || [];
    const set = (g: ConditionalRule[][]) => onChange(g.length ? g : false);

    if (candidates.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'Dodaj inne pole z nazwą, aby ustawić warunki wyświetlania.',
                )}
            </p>
        );
    }

    const newRule = (): ConditionalRule => ({
        field: candidates[0].name,
        operator: '==',
        value: '',
    });

    return (
        <div className="space-y-3">
            {groups.map((group, gi) => (
                <div key={gi} className="space-y-2 rounded-lg border p-3">
                    {gi > 0 && (
                        <p className="-mt-6 mb-1 w-fit bg-background px-2 text-xs font-medium text-muted-foreground uppercase">
                            {t('lub')}
                        </p>
                    )}
                    {group.map((rule, ri) => (
                        <div
                            key={ri}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="w-4 text-xs text-muted-foreground">
                                {ri > 0 ? t('i') : ''}
                            </span>
                            <select
                                className={`${nativeClass} w-44`}
                                value={rule.field}
                                onChange={(e) =>
                                    set(
                                        groups.map((g, i) =>
                                            i === gi
                                                ? g.map((r, j) =>
                                                      j === ri
                                                          ? {
                                                                ...r,
                                                                field: e.target
                                                                    .value,
                                                            }
                                                          : r,
                                                  )
                                                : g,
                                        ),
                                    )
                                }
                            >
                                {candidates.map((c) => (
                                    <option key={c.name} value={c.name}>
                                        {c.label || c.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={`${nativeClass} w-52`}
                                value={rule.operator}
                                onChange={(e) =>
                                    set(
                                        groups.map((g, i) =>
                                            i === gi
                                                ? g.map((r, j) =>
                                                      j === ri
                                                          ? {
                                                                ...r,
                                                                operator:
                                                                    e.target
                                                                        .value,
                                                            }
                                                          : r,
                                                  )
                                                : g,
                                        ),
                                    )
                                }
                            >
                                {operators.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                            {!rule.operator.endsWith('empty') && (
                                <Input
                                    className="w-40"
                                    value={rule.value ?? ''}
                                    placeholder={t('wartość')}
                                    onChange={(e) =>
                                        set(
                                            groups.map((g, i) =>
                                                i === gi
                                                    ? g.map((r, j) =>
                                                          j === ri
                                                              ? {
                                                                    ...r,
                                                                    value: e
                                                                        .target
                                                                        .value,
                                                                }
                                                              : r,
                                                      )
                                                    : g,
                                            ),
                                        )
                                    }
                                />
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Usuń regułę')}
                                onClick={() =>
                                    set(
                                        groups
                                            .map((g, i) =>
                                                i === gi
                                                    ? g.filter(
                                                          (_, j) => j !== ri,
                                                      )
                                                    : g,
                                            )
                                            .filter((g) => g.length),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            set(
                                groups.map((g, i) =>
                                    i === gi ? [...g, newRule()] : g,
                                ),
                            )
                        }
                    >
                        <Plus /> {t('i')}
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => set([...groups, [newRule()]])}
            >
                <Plus />{' '}
                {groups.length ? t('Dodaj grupę „lub”') : t('Dodaj warunek')}
            </Button>
        </div>
    );
}

const params: [string, string][] = [
    ['content_type', t('Typ treści')],
    ['status', t('Status')],
    ['template', t('Szablon')],
    ['parent', t('Strona nadrzędna')],
    ['term', t('Termin')],
];

type LocationProps = {
    value: LocationRule[][];
    onChange: (v: LocationRule[][]) => void;
    contentTypes: { key: string; label_plural: string }[];
    terms: { id: number; name: string; taxonomy: string | null }[];
    parents: { id: number; title: string }[];
};

export function LocationEditor({
    value,
    onChange,
    contentTypes,
    terms,
    parents,
}: LocationProps) {
    const options = (param: string): [string, string][] | null => {
        switch (param) {
            case 'content_type':
                return contentTypes.map((t) => [t.key, t.label_plural]);
            case 'status':
                return [
                    ['draft', t('Szkic')],
                    ['published', t('Opublikowana')],
                    ['scheduled', t('Zaplanowana')],
                ];
            case 'parent':
                return parents.map((p) => [String(p.id), p.title]);
            case 'term':
                return terms.map((t) => [
                    String(t.id),
                    t.taxonomy ? `${t.taxonomy}: ${t.name}` : t.name,
                ]);
            default:
                return null;
        }
    };

    const newRule = (): LocationRule => ({
        param: 'content_type',
        operator: '==',
        value: contentTypes[0]?.key ?? '',
    });
    const patch = (gi: number, ri: number, p: Partial<LocationRule>) =>
        onChange(
            value.map((g, i) =>
                i === gi ? g.map((r, j) => (j === ri ? { ...r, ...p } : r)) : g,
            ),
        );

    return (
        <div className="space-y-3">
            {value.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('Bez reguł grupa nigdzie się nie wyświetli.')}
                </p>
            )}
            {value.map((group, gi) => (
                <div key={gi} className="space-y-2 rounded-lg border p-3">
                    {gi > 0 && (
                        <p className="-mt-6 mb-1 w-fit bg-background px-2 text-xs font-medium text-muted-foreground uppercase">
                            {t('lub')}
                        </p>
                    )}
                    {group.map((rule, ri) => {
                        const opts = options(rule.param);

                        return (
                            <div
                                key={ri}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <span className="w-4 text-xs text-muted-foreground">
                                    {ri > 0 ? t('i') : ''}
                                </span>
                                <select
                                    className={`${nativeClass} w-44`}
                                    value={rule.param}
                                    onChange={(e) =>
                                        patch(gi, ri, {
                                            param: e.target.value,
                                            value:
                                                options(
                                                    e.target.value,
                                                )?.[0]?.[0] ?? '',
                                        })
                                    }
                                >
                                    {params.map(([v, l]) => (
                                        <option key={v} value={v}>
                                            {l}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className={`${nativeClass} w-36`}
                                    value={rule.operator}
                                    onChange={(e) =>
                                        patch(gi, ri, {
                                            operator: e.target.value as
                                                | '=='
                                                | '!=',
                                        })
                                    }
                                >
                                    <option value="==">
                                        {t('jest równe')}
                                    </option>
                                    <option value="!=">
                                        {t('nie jest równe')}
                                    </option>
                                </select>
                                {opts ? (
                                    <select
                                        className={`${nativeClass} w-56`}
                                        value={rule.value}
                                        onChange={(e) =>
                                            patch(gi, ri, {
                                                value: e.target.value,
                                            })
                                        }
                                    >
                                        {opts.map(([v, l]) => (
                                            <option key={v} value={v}>
                                                {l}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <Input
                                        className="w-56"
                                        value={rule.value}
                                        placeholder={t('np. landing')}
                                        onChange={(e) =>
                                            patch(gi, ri, {
                                                value: e.target.value,
                                            })
                                        }
                                    />
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Usuń regułę')}
                                    onClick={() =>
                                        onChange(
                                            value
                                                .map((g, i) =>
                                                    i === gi
                                                        ? g.filter(
                                                              (_, j) =>
                                                                  j !== ri,
                                                          )
                                                        : g,
                                                )
                                                .filter((g) => g.length),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        );
                    })}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            onChange(
                                value.map((g, i) =>
                                    i === gi ? [...g, newRule()] : g,
                                ),
                            )
                        }
                    >
                        <Plus /> {t('i')}
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...value, [newRule()]])}
            >
                <Plus />{' '}
                {value.length ? t('Dodaj grupę „lub”') : t('Dodaj regułę')}
            </Button>
        </div>
    );
}
