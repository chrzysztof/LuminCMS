import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ImagePlus,
    Loader2,
    PenTool,
    Trash2,
    X,
} from 'lucide-react';
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
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link
                                href={cmsUrl.contentIndex(type.key)}
                                aria-label={t('Wróć do listy')}
                            >
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {content
                                ? t('Edytuj: {type}', {
                                      type: type.label.toLowerCase(),
                                  })
                                : t('Dodaj: {type}', {
                                      type: type.label.toLowerCase(),
                                  })}
                        </h1>
                        {content && <StatusBadge status={content.status} />}
                    </div>
                    <div className="flex items-center gap-2">
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
                                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <span>
                                            /
                                            {type.url_prefix
                                                ? `${type.url_prefix}/`
                                                : ''}
                                        </span>
                                        <Input
                                            id="slug"
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
                                {supports('excerpt') && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="excerpt">
                                            {t('Zajawka')}
                                        </Label>
                                        <textarea
                                            id="excerpt"
                                            rows={3}
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
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {supports('editor') && (
                            <Card className="gap-4">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('Treść')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {content?.has_builder ? (
                                        <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                            {t(
                                                'Treść tej strony jest edytowana w edytorze wizualnym.',
                                            )}
                                        </p>
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
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setPickingFeatured(true)}
                                    >
                                        <ImagePlus />{' '}
                                        {featured
                                            ? t('Zmień obraz')
                                            : t('Wybierz obraz')}
                                    </Button>
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
