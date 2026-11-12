import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { toDraft } from '@/components/cms/field-builder/draft';
import { FieldEditor } from '@/components/cms/field-builder/field-editor';
import { LocationEditor } from '@/components/cms/field-builder/logic-editors';
import {
    Row,
    SelectSetting,
    Toggle,
} from '@/components/cms/field-builder/setting-inputs';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cmsUrl } from '@/lib/cms-urls';
import type {
    FieldDraft,
    FieldTypeInfo,
    GroupGeneral,
    LocationRule,
} from '@/types/field-builder';
import { t } from '@/lib/i18n';

type GroupData = GroupGeneral & {
    id: number;
    key: string;
    fields: Record<string, unknown>[];
    location_rules: LocationRule[][];
};

type Props = {
    group: GroupData | null;
    fieldTypes: FieldTypeInfo[];
    contentTypes: { key: string; label_plural: string }[];
    terms: { id: number; name: string; taxonomy: string | null }[];
    parents: { id: number; title: string }[];
};

export default function FieldGroupEdit({
    group,
    fieldTypes,
    contentTypes,
    terms,
    parents,
}: Props) {
    const [general, setGeneral] = useState<GroupGeneral>({
        title: group?.title ?? '',
        position: group?.position ?? 'normal',
        style: group?.style ?? 'default',
        label_placement: group?.label_placement ?? 'top',
        instruction_placement: group?.instruction_placement ?? 'label',
        active: group?.active ?? true,
    });
    const [fields, setFields] = useState<FieldDraft[]>(() =>
        (group?.fields ?? []).map(toDraft),
    );
    const [location, setLocation] = useState<LocationRule[][]>(
        group?.location_rules ??
            (contentTypes[0]
                ? [
                      [
                          {
                              param: 'content_type',
                              operator: '==',
                              value: contentTypes[0].key,
                          },
                      ],
                  ]
                : []),
    );
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);

    const patchField = (id: string, patch: Record<string, unknown>) =>
        setFields((list) =>
            list.map((f) => {
                if (f._id !== id) return f;
                if ('__replace' in patch)
                    return { ...(patch.__replace as FieldDraft) };

                return { ...f, ...patch } as FieldDraft;
            }),
        );

    const addField = () => {
        const base = fieldTypes.find((t) => t.name === 'text') ?? fieldTypes[0];
        setFields((l) => [
            ...l,
            toDraft({ type: base.name, ...base.settings }),
        ]);
    };

    const move = (i: number, dir: -1 | 1) =>
        setFields((l) => {
            const next = [...l];
            [next[i], next[i + dir]] = [next[i + dir], next[i]];

            return next;
        });

    const duplicate = (i: number) =>
        setFields((l) => {
            const copy = toDraft({
                ...l[i],
                name: l[i].name ? `${l[i].name}_kopia` : '',
                label: l[i].label
                    ? t('{name} (kopia)', { name: l[i].label })
                    : '',
            });
            const next = [...l];
            next.splice(i + 1, 0, copy);

            return next;
        });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...general,
            fields,
            location_rules: location,
        } as unknown as Record<string, never>;
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (errs: Record<string, string>) => setErrors(errs),
            onSuccess: () => setErrors({}),
        };

        if (group) router.put(cmsUrl.fieldUpdate(group.id), payload, options);
        else router.post(cmsUrl.fieldIndex, payload, options);
    };

    const remove = () => {
        if (
            group &&
            confirm(
                t(
                    'Usunąć tę grupę pól? Zapisane wartości w treściach zostaną w bazie.',
                ),
            )
        )
            router.delete(cmsUrl.fieldUpdate(group.id));
    };

    const errorList = Object.entries(errors).filter(
        ([k]) => !k.startsWith('fields.'),
    );

    return (
        <>
            <Head
                title={
                    group
                        ? t('Grupa pól: {name}', { name: group.title })
                        : t('Nowa grupa pól')
                }
            />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link
                                href={cmsUrl.fieldIndex}
                                aria-label={t('Wróć do listy')}
                            >
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {group
                                ? t('Edytuj grupę pól')
                                : t('Nowa grupa pól')}
                        </h1>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="animate-spin" />}{' '}
                        {t('Zapisz grupę')}
                    </Button>
                </div>

                <div className="grid items-start gap-5 xl:grid-cols-[1fr_22rem]">
                    <div className="min-w-0 space-y-5">
                        <Card>
                            <CardContent className="space-y-1.5">
                                <Input
                                    value={general.title}
                                    placeholder={t(
                                        'Tytuł grupy, np. Dane strony głównej',
                                    )}
                                    className="h-11 text-lg font-medium"
                                    onChange={(e) =>
                                        setGeneral({
                                            ...general,
                                            title: e.target.value,
                                        })
                                    }
                                    autoFocus={!group}
                                />
                                {errors.title && (
                                    <p className="text-xs text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                                {errorList.map(([k, m]) => (
                                    <p
                                        key={k}
                                        className="text-xs text-destructive"
                                    >
                                        {m}
                                    </p>
                                ))}
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Pola ({n})', { n: fields.length })}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {fields.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        {t('Ta grupa nie ma jeszcze pól.')}
                                    </p>
                                )}
                                {fields.map((f, i) => (
                                    <FieldEditor
                                        key={f._id}
                                        field={f}
                                        index={i}
                                        total={fields.length}
                                        types={fieldTypes}
                                        siblings={fields}
                                        errors={errors}
                                        errorPrefix={`fields.${i}`}
                                        onChange={(p) => patchField(f._id, p)}
                                        onMove={(d) => move(i, d)}
                                        onDuplicate={() => duplicate(i)}
                                        onRemove={() =>
                                            setFields((l) =>
                                                l.filter(
                                                    (x) => x._id !== f._id,
                                                ),
                                            )
                                        }
                                    />
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addField}
                                >
                                    <Plus /> {t('Dodaj pole')}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Gdzie pokazać tę grupę')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <LocationEditor
                                    value={location}
                                    onChange={setLocation}
                                    contentTypes={contentTypes}
                                    terms={terms}
                                    parents={parents}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Ustawienia grupy')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Toggle
                                label={t('Grupa aktywna')}
                                checked={general.active}
                                onChange={(v) =>
                                    setGeneral({ ...general, active: v })
                                }
                            />
                            <SelectSetting
                                label={t('Położenie w edytorze')}
                                value={general.position}
                                options={[
                                    ['after_title', t('Pod tytułem')],
                                    ['normal', t('W treści')],
                                    ['side', t('W panelu bocznym')],
                                ]}
                                onChange={(v) =>
                                    setGeneral({
                                        ...general,
                                        position: v as GroupGeneral['position'],
                                    })
                                }
                            />
                            <SelectSetting
                                label={t('Styl')}
                                value={general.style}
                                options={[
                                    ['default', t('Standardowy (w ramce)')],
                                    ['seamless', t('Bez ramki')],
                                ]}
                                onChange={(v) =>
                                    setGeneral({
                                        ...general,
                                        style: v as GroupGeneral['style'],
                                    })
                                }
                            />
                            <SelectSetting
                                label={t('Położenie etykiet')}
                                value={general.label_placement}
                                options={[
                                    ['top', t('Nad polem')],
                                    ['left', t('Z lewej')],
                                ]}
                                onChange={(v) =>
                                    setGeneral({
                                        ...general,
                                        label_placement:
                                            v as GroupGeneral['label_placement'],
                                    })
                                }
                            />
                            <SelectSetting
                                label={t('Położenie instrukcji')}
                                value={general.instruction_placement}
                                options={[
                                    ['label', t('Pod etykietą')],
                                    ['field', t('Pod polem')],
                                ]}
                                onChange={(v) =>
                                    setGeneral({
                                        ...general,
                                        instruction_placement:
                                            v as GroupGeneral['instruction_placement'],
                                    })
                                }
                            />
                            {group && (
                                <Row label={t('Klucz grupy')}>
                                    <code className="rounded bg-muted px-2 py-1 text-xs">
                                        {group.key}
                                    </code>
                                </Row>
                            )}
                            {group && (
                                <div className="flex flex-wrap gap-2 border-t pt-4">
                                    <Button
                                        asChild
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                    >
                                        <a href={cmsUrl.fieldExport(group.id)}>
                                            <Download /> {t('Eksport JSON')}
                                        </a>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={remove}
                                    >
                                        {t('Usuń grupę')}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}
