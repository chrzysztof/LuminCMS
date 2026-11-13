import { Head, Link, router, useForm } from '@inertiajs/react';
import { ImagePlus, Loader2, PenTool, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { FieldList } from '@/components/cms/fields';
import { MediaPicker } from '@/components/cms/media-picker';
import { WysiwygEditor } from '@/components/cms/wysiwyg-editor';
import { SeoPanel, type SeoValues } from '@/components/cms/seo-panel';
import { TermsPanel, type TaxonomyOption } from '@/components/cms/terms-panel';
import { StatusBadge } from '@/components/cms/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import type { SeoSite } from '@/lib/seo';
import { cn } from '@/lib/utils';
import type {
    ContentStatus,
    ContentTypeInfo,
    FieldGroupData,
    MediaItem,
} from '@/types/cms';
import { t } from '@/lib/i18n';

type ContentData = {
    id: number;
    title: string;
    slug: string;
    status: ContentStatus;
    excerpt: string | null;
    body: string;
    has_builder: boolean;
    updated_at?: string | null;
    parent_id: number | null;
    template: string;
    featured_media_id: number | null;
    published_at: string | null;
};

/** Wartości pól zapisywane w formularzu (płaskie, bez rekurencji — Inertia nie lubi głębokich typów). */
type FieldValue = string | number | boolean | null | (string | number)[];

type Props = {
    type: ContentTypeInfo;
    content: ContentData | null;
    values: Record<string, unknown>;
    seo: SeoValues;
    seoSite: SeoSite;
    groups: FieldGroupData[];
    media: Record<number, MediaItem>;
    parents: { id: number; title: string }[];
    templates: { key: string; label: string }[];
    taxonomies: TaxonomyOption[];
    termIds: number[];
    canManageTerms: boolean;
    canPublish: boolean;
    canDelete: boolean;
};

const nativeClass =
    'border-input bg-transparent flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function ContentEdit({
    type,
    content,
    values,
    seo,
    seoSite,
    groups,
    media: initialMedia,
    parents,
    templates,
    taxonomies,
    termIds,
    canManageTerms,
    canPublish,
    canDelete,
}: Props) {
    const [media, setMedia] = useState<Record<number, MediaItem>>(initialMedia);
    const [pickingFeatured, setPickingFeatured] = useState(false);

    const form = useForm({
        title: content?.title ?? '',
        slug: content?.slug ?? '',
        status: content?.status ?? ('draft' as ContentStatus),
        excerpt: content?.excerpt ?? '',
        body: content?.body ?? '',
        content_mode: content?.has_builder ? 'visual' : 'text',
        parent_id: content?.parent_id ?? (null as number | null),
        featured_media_id:
            content?.featured_media_id ?? (null as number | null),
        published_at: content?.published_at ?? '',
        template: content?.template ?? 'default',
        term_ids: termIds,
        fields: values as Record<string, FieldValue>,
        seo: seo as SeoValues,
    });

    const errors = form.errors as Record<string, string | undefined>;
    const rememberMedia = (items: MediaItem[]) =>
        setMedia((m) => ({
            ...m,
            ...Object.fromEntries(items.map((i) => [i.id, i])),
        }));
    const setField = (name: string, value: unknown) =>
        form.setData('fields', {
            ...form.data.fields,
            [name]: value as FieldValue,
        });
    const supports = (feature: string) => type.supports?.includes(feature);
    const featured = form.data.featured_media_id
        ? media[form.data.featured_media_id]
        : null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (content) {
            form.put(cmsUrl.contentUpdate(type.key, content.id), {
                preserveScroll: true,
            });
        } else {
            form.post(cmsUrl.contentIndex(type.key));
        }
    };

    const remove = () => {
        if (content && confirm(t('Przenieść do kosza?')))
            router.delete(cmsUrl.contentUpdate(type.key, content.id));
    };

    const renderGroups = (position: FieldGroupData['position']) =>
        groups
            .filter((g) => g.position === position)
            .map((g) => (
                <Card
                    key={g.id}
                    className={cn(
                        'gap-4',
                        g.style === 'seamless' &&
                            'border-0 bg-transparent py-0 shadow-none',
                    )}
                >
                    {g.style !== 'seamless' && (
                        <CardHeader>
                            <CardTitle className="text-base">
                                {g.title}
                            </CardTitle>
                            <p className="text-[13px] text-muted-foreground">
                                {t(
                                    'Pola własne przypisane do tego typu treści',
                                )}
                            </p>
                        </CardHeader>
                    )}
                    <CardContent
                        className={cn(g.style === 'seamless' && 'px-0')}
                    >
                        <FieldList
                            fields={g.fields}
                            values={form.data.fields}
                            errors={errors}
                            media={media}
                            rememberMedia={rememberMedia}
                            onChange={setField}
                            labelPlacement={g.label_placement}
                            groupId={g.id}
                        />
                    </CardContent>
                </Card>
            ));

    return (
        <>
            <Head
                title={
                    content
                        ? t('Edycja: {name}', { name: content.title })
                        : t('Nowa pozycja: {type}', {
                              type: type.label.toLowerCase(),
                          })
                }
            />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6 lg:px-8 [&_[data-slot=card-content]]:px-5 [&_[data-slot=card-header]]:px-5 [&_[data-slot=card-title]]:text-base [&_[data-slot=card]]:gap-4 [&_[data-slot=card]]:rounded-[14px] [&_[data-slot=card]]:border-[#E7E7EA] [&_[data-slot=card]]:py-5 [&_[data-slot=card]]:shadow-none [&_input:not([type=checkbox]):not([type=radio]):not([type=color])]:h-10 [&_input:not([type=checkbox]):not([type=radio]):not([type=color])]:rounded-[10px] [&_select]:h-10 [&_select]:rounded-[10px] [&_select]:border-[#D9D9DE] [&_textarea]:rounded-[10px]"
            >
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="text-[13px] text-muted-foreground">
                            <Link
                                href={cmsUrl.contentIndex(type.key)}
                                className="hover:text-foreground"
                            >
                                {type.label_plural}
                            </Link>
                            {' / '}
                            {content ? t('Edycja') : t('Nowa pozycja')}
                        </div>
                        <div className="mt-1 flex items-center gap-3">
                            <h1 className="text-[26px] leading-tight font-bold tracking-tight">
                                {form.data.title ||
                                    (content
                                        ? t('Edytuj: {type}', {
                                              type: type.label.toLowerCase(),
                                          })
                                        : t('Dodaj: {type}', {
                                              type: type.label.toLowerCase(),
                                          }))}
                            </h1>
                            {content && <StatusBadge status={content.status} />}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        {content?.updated_at && (
                            <span className="text-[13px] text-muted-foreground">
                                {t('Ostatni zapis')}:{' '}
                                {new Date(content.updated_at).toLocaleString(
                                    undefined,
                                    {
                                        day: '2-digit',
                                        month: '2-digit',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    },
                                )}
                            </span>
                        )}
                        {content && supports('builder') && (
                            <Button asChild variant="outline">
                                <Link
                                    href={cmsUrl.contentBuilder(
                                        type.key,
                                        content.id,
                                    )}
                                >
                                    <PenTool /> {t('Edytor wizualny')}
                                </Link>
                            </Button>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="animate-spin" />
                            )}
                            {form.data.status === 'published'
                                ? content
                                    ? t('Zaktualizuj')
                                    : t('Opublikuj')
                                : t('Zapisz')}
                        </Button>
                    </div>
                </div>

                <div className="grid items-start gap-5 xl:grid-cols-[1fr_20rem]">
                    <div className="min-w-0 space-y-5">
                        <Card className="gap-4">
                            <CardContent className="space-y-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="title">{t('Tytuł')}</Label>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(e) =>
                                            form.setData(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        className="h-11 text-lg font-medium"
                                        autoFocus={!content}
                                    />
                                    {errors.title && (
                                        <p className="text-xs text-destructive">
                                            {errors.title}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="slug">
                                        {t('Adres (slug)')}
                                    </Label>
                                    <div className="flex h-10 items-center overflow-hidden rounded-[10px] border border-[#D9D9DE] text-sm text-muted-foreground focus-within:ring-[3px] focus-within:ring-ring/30">
                                        <span className="flex h-full items-center border-r border-[#D9D9DE] bg-muted/60 px-3 text-[13px]">
                                            /
                                            {type.url_prefix
                                                ? `${type.url_prefix}/`
                                                : ''}
                                        </span>
                                        <Input
                                            id="slug"
                                            className="h-full flex-1 rounded-none border-0 shadow-none focus-visible:ring-0"
                                            value={form.data.slug}
                                            placeholder={t(
                                                'generowany z tytułu',
                                            )}
                                            onChange={(e) =>
                                                form.setData(
                                                    'slug',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    {errors.slug && (
                                        <p className="text-xs text-destructive">
                                            {errors.slug}
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {supports('editor') && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <CardTitle className="text-base">
                                            {type.hierarchical
                                                ? t('Treść strony')
                                                : t('Treść')}
                                        </CardTitle>
                                        {supports('builder') && content && (
                                            <div
                                                role="tablist"
                                                className="inline-flex rounded-[10px] bg-muted p-1 text-sm"
                                            >
                                                {(
                                                    [
                                                        [
                                                            'text',
                                                            t(
                                                                'Edytor tekstowy',
                                                            ),
                                                        ],
                                                        [
                                                            'visual',
                                                            t(
                                                                'Edytor wizualny',
                                                            ),
                                                        ],
                                                    ] as const
                                                ).map(([mode, label]) => (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        role="tab"
                                                        aria-selected={
                                                            form.data
                                                                .content_mode ===
                                                            mode
                                                        }
                                                        onClick={() => {
                                                            if (
                                                                mode ===
                                                                    'text' &&
                                                                content.has_builder &&
                                                                form.data
                                                                    .content_mode !==
                                                                    'text' &&
                                                                !confirm(
                                                                    t(
                                                                        'Po zapisaniu układ z edytora wizualnego zostanie zastąpiony treścią z edytora tekstowego. Kontynuować?',
                                                                    ),
                                                                )
                                                            )
                                                                return;
                                                            form.setData(
                                                                'content_mode',
                                                                mode,
                                                            );
                                                        }}
                                                        className={cn(
                                                            'rounded-lg px-3 py-1 font-medium',
                                                            form.data
                                                                .content_mode ===
                                                                mode
                                                                ? 'bg-background shadow-sm'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {label}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {form.data.content_mode === 'visual' &&
                                    content ? (
                                        <div className="flex flex-col items-start gap-3 rounded-xl border border-dashed p-5">
                                            <p className="text-sm text-muted-foreground">
                                                {content.has_builder
                                                    ? t(
                                                          'Treść tej strony jest edytowana w edytorze wizualnym.',
                                                      )
                                                    : t(
                                                          'Ta strona nie ma jeszcze układu. Otwórz edytor wizualny, aby go zbudować.',
                                                      )}
                                            </p>
                                            <Button asChild>
                                                <Link
                                                    href={cmsUrl.contentBuilder(
                                                        type.key,
                                                        content.id,
                                                    )}
                                                >
                                                    <PenTool />{' '}
                                                    {t(
                                                        'Otwórz edytor wizualny',
                                                    )}
                                                </Link>
                                            </Button>
                                        </div>
                                    ) : (
                                        <WysiwygEditor
                                            id="body"
                                            value={form.data.body}
                                            onChange={(html) =>
                                                form.setData('body', html)
                                            }
                                            minHeight={320}
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {supports('excerpt') && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('Zajawka')}
                                    </CardTitle>
                                    <p className="text-[13px] text-muted-foreground">
                                        {t(
                                            'Krótki opis na listach wpisów i w wynikach wyszukiwania.',
                                        )}
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <textarea
                                        id="excerpt"
                                        rows={4}
                                        value={form.data.excerpt}
                                        onChange={(e) =>
                                            form.setData(
                                                'excerpt',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            nativeClass,
                                            'h-auto py-2',
                                        )}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {renderGroups('after_title')}
                        {renderGroups('normal')}

                        {supports('seo') && (
                            <SeoPanel
                                seo={form.data.seo}
                                onChange={(patch) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        ...patch,
                                    })
                                }
                                site={seoSite}
                                title={form.data.title}
                                slug={form.data.slug}
                                excerpt={form.data.excerpt}
                                media={media}
                                rememberMedia={rememberMedia}
                                errors={errors}
                                isHome={
                                    type.key === 'page' &&
                                    form.data.slug === 'home' &&
                                    !form.data.parent_id
                                }
                            />
                        )}
                    </div>

                    <div className="min-w-0 space-y-5">
                        <Card className="gap-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Publikacja')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="status">
                                        {t('Status')}
                                    </Label>
                                    <select
                                        id="status"
                                        className={nativeClass}
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target.value as ContentStatus,
                                            )
                                        }
                                    >
                                        <option value="draft">
                                            {t('Szkic')}
                                        </option>
                                        {!canPublish &&
                                        content &&
                                        (content.status === 'published' ||
                                            content.status === 'scheduled') ? (
                                            <option value={content.status}>
                                                {content.status === 'published'
                                                    ? t('Opublikowana')
                                                    : t('Zaplanowana')}
                                            </option>
                                        ) : canPublish ? (
                                            <>
                                                <option value="published">
                                                    {t('Opublikowana')}
                                                </option>
                                                <option value="scheduled">
                                                    {t('Zaplanowana')}
                                                </option>
                                            </>
                                        ) : (
                                            <option value="pending">
                                                {t('Oczekuje na przegląd')}
                                            </option>
                                        )}
                                    </select>
                                </div>
                                {form.data.status === 'scheduled' && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="published_at">
                                            {t('Data publikacji')}
                                        </Label>
                                        <Input
                                            id="published_at"
                                            type="datetime-local"
                                            value={form.data.published_at}
                                            onChange={(e) =>
                                                form.setData(
                                                    'published_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.published_at && (
                                            <p className="text-xs text-destructive">
                                                {errors.published_at}
                                            </p>
                                        )}
                                    </div>
                                )}
                                {type.hierarchical && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="parent">
                                            {t('Strona nadrzędna')}
                                        </Label>
                                        <select
                                            id="parent"
                                            className={nativeClass}
                                            value={form.data.parent_id ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'parent_id',
                                                    e.target.value
                                                        ? Number(e.target.value)
                                                        : null,
                                                )
                                            }
                                        >
                                            <option value="">
                                                {t('— brak —')}
                                            </option>
                                            {parents.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.title}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {content && canDelete && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={remove}
                                    >
                                        <Trash2 /> {t('Przenieś do kosza')}
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        {templates.length > 1 && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('Szablon')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <select
                                        className={nativeClass}
                                        value={form.data.template}
                                        onChange={(e) =>
                                            form.setData(
                                                'template',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {templates.map((tpl) => (
                                            <option
                                                key={tpl.key}
                                                value={tpl.key}
                                            >
                                                {tpl.label}
                                            </option>
                                        ))}
                                    </select>
                                </CardContent>
                            </Card>
                        )}

                        <TermsPanel
                            taxonomies={taxonomies}
                            value={form.data.term_ids}
                            canManage={canManageTerms}
                            onChange={(ids) => form.setData('term_ids', ids)}
                        />

                        {supports('thumbnail') && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('Obraz wyróżniający')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {featured && (
                                        <div className="group relative overflow-hidden rounded-lg border">
                                            <img
                                                src={featured.url}
                                                alt={featured.alt}
                                                className="w-full"
                                            />
                                            <button
                                                type="button"
                                                aria-label={t('Usuń obraz')}
                                                onClick={() =>
                                                    form.setData(
                                                        'featured_media_id',
                                                        null,
                                                    )
                                                }
                                                className="absolute top-2 right-2 rounded-full bg-black/70 p-1 text-white"
                                            >
                                                <X className="size-4" />
                                            </button>
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setPickingFeatured(true)}
                                        className={cn(
                                            'flex w-full flex-col items-center justify-center gap-2 rounded-xl border-[1.5px] border-dashed border-[#C9C9D0] bg-[#FAFAFB] text-[13px] text-muted-foreground hover:bg-muted/60',
                                            featured ? 'h-10 flex-row' : 'h-36',
                                        )}
                                    >
                                        <ImagePlus
                                            className={
                                                featured ? 'size-4' : 'size-7'
                                            }
                                            strokeWidth={1.5}
                                        />
                                        {featured
                                            ? t('Zmień obraz')
                                            : t('Wybierz z biblioteki mediów')}
                                    </button>
                                    <p className="text-xs leading-relaxed text-muted-foreground">
                                        {t(
                                            'Obraz zostanie automatycznie skompresowany i zapisany jako WebP w rozmiarach zdefiniowanych w ustawieniach mediów.',
                                        )}
                                    </p>
                                    <MediaPicker
                                        open={pickingFeatured}
                                        onOpenChange={setPickingFeatured}
                                        imagesOnly
                                        onPick={(items) => {
                                            rememberMedia(items);
                                            form.setData(
                                                'featured_media_id',
                                                items[0]?.id ?? null,
                                            );
                                        }}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        {renderGroups('side')}
                    </div>
                </div>
            </form>
        </>
    );
}
