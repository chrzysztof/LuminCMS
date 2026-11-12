import { Check, ImagePlus, X } from 'lucide-react';
import { useState } from 'react';
import { MediaPicker } from '@/components/cms/media-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    analyse,
    applyTemplate,
    DESCRIPTION_MAX,
    limitWords,
    TITLE_MAX,
    type SeoSite,
} from '@/lib/seo';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

export type SeoValues = Record<string, string | number | boolean | null>;

type Props = {
    seo: SeoValues;
    onChange: (patch: SeoValues) => void;
    site: SeoSite;
    title: string;
    slug: string;
    excerpt: string;
    media: Record<number, MediaItem>;
    rememberMedia: (items: MediaItem[]) => void;
    errors: Record<string, string | undefined>;
    isHome: boolean;
};

const nativeClass =
    'border-input bg-transparent flex w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const str = (v: unknown) => (typeof v === 'string' ? v : '');

function Counter({ value, max }: { value: number; max: number }) {
    return (
        <span
            className={cn(
                'text-xs tabular-nums',
                value > max ? 'text-destructive' : 'text-muted-foreground',
            )}
        >
            {value}/{max}
        </span>
    );
}

function Toggle({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                className="size-4 accent-brand"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}

/** Panel SEO treści: podgląd w Google, pola meta, Open Graph i prosta analiza frazy kluczowej. */
export function SeoPanel({
    seo,
    onChange,
    site,
    title,
    slug,
    excerpt,
    media,
    rememberMedia,
    errors,
    isHome,
}: Props) {
    const [picking, setPicking] = useState(false);
    const customTitle = str(seo.title);
    const customDescription = str(seo.description);

    const shownTitle =
        customTitle === '' && isHome
            ? [site.site_name, site.tagline]
                  .filter(Boolean)
                  .join(` ${site.separator} `)
            : applyTemplate(
                  site.title_template,
                  customTitle || title || t('Tytuł strony'),
                  site,
              );
    const fallbackDescription = limitWords(
        excerpt.trim() || site.body,
        DESCRIPTION_MAX,
    );
    const shownDescription = customDescription || fallbackDescription;
    const path = isHome
        ? ''
        : [site.url_prefix, slug || 'adres-strony'].filter(Boolean).join('/');
    const ogImage =
        typeof seo.og_image_id === 'number' ? media[seo.og_image_id] : null;
    const checks = analyse({
        keyword: str(seo.focus_keyword),
        title: shownTitle,
        description: shownDescription,
        slug,
        body: `${excerpt} ${site.body}`,
    });

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-base">SEO</CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
                {site.noindex_site && (
                    <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {t(
                            'Cała witryna jest ukryta przed wyszukiwarkami (Ustawienia → SEO).',
                        )}
                    </p>
                )}

                <div
                    className="rounded-lg border bg-card p-4"
                    aria-label={t('Podgląd w Google')}
                >
                    <p className="truncate text-xs text-muted-foreground">
                        {site.base_url.replace(/^https?:\/\//, '')}
                        {path ? ` › ${path.split('/').join(' › ')}` : ''}
                    </p>
                    <p className="mt-0.5 line-clamp-1 text-lg text-[#1a0dab] dark:text-[#8ab4f8]">
                        {shownTitle}
                    </p>
                    <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">
                        {shownDescription ||
                            t(
                                'Brak opisu — wyszukiwarka wybierze fragment treści.',
                            )}
                    </p>
                </div>

                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="seo-title">{t('Tytuł SEO')}</Label>
                        <Counter value={shownTitle.length} max={TITLE_MAX} />
                    </div>
                    <Input
                        id="seo-title"
                        value={customTitle}
                        placeholder={title || t('Domyślnie: tytuł treści')}
                        onChange={(e) => onChange({ title: e.target.value })}
                    />
                    {errors['seo.title'] && (
                        <p className="text-xs text-destructive">
                            {errors['seo.title']}
                        </p>
                    )}
                </div>

                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="seo-description">
                            {t('Opis meta')}
                        </Label>
                        <Counter
                            value={shownDescription.length}
                            max={DESCRIPTION_MAX}
                        />
                    </div>
                    <textarea
                        id="seo-description"
                        rows={3}
                        className={nativeClass}
                        value={customDescription}
                        placeholder={t(
                            'Domyślnie: zajawka albo początek treści',
                        )}
                        onChange={(e) =>
                            onChange({ description: e.target.value })
                        }
                    />
                    {errors['seo.description'] && (
                        <p className="text-xs text-destructive">
                            {errors['seo.description']}
                        </p>
                    )}
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="seo-keyword">{t('Fraza kluczowa')}</Label>
                    <Input
                        id="seo-keyword"
                        value={str(seo.focus_keyword)}
                        onChange={(e) =>
                            onChange({ focus_keyword: e.target.value })
                        }
                    />
                    <ul className="mt-1 space-y-1">
                        {checks.map((c) => (
                            <li
                                key={c.text}
                                className="flex items-start gap-2 text-xs"
                            >
                                {c.ok ? (
                                    <Check className="mt-0.5 size-3.5 shrink-0 text-emerald-600" />
                                ) : (
                                    <X className="mt-0.5 size-3.5 shrink-0 text-amber-600" />
                                )}
                                <span
                                    className={
                                        c.ok ? '' : 'text-muted-foreground'
                                    }
                                >
                                    {c.text}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <details
                    className="group rounded-lg border p-3"
                    open={Boolean(
                        seo.canonical ||
                        seo.og_title ||
                        seo.og_description ||
                        seo.og_image_id,
                    )}
                >
                    <summary className="cursor-pointer text-sm font-medium">
                        {t('Zaawansowane i media społecznościowe')}
                    </summary>
                    <div className="mt-4 space-y-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="seo-canonical">
                                {t('Adres kanoniczny (canonical)')}
                            </Label>
                            <Input
                                id="seo-canonical"
                                type="url"
                                value={str(seo.canonical)}
                                placeholder={t('Domyślnie: adres tej strony')}
                                onChange={(e) =>
                                    onChange({ canonical: e.target.value })
                                }
                            />
                            {errors['seo.canonical'] && (
                                <p className="text-xs text-destructive">
                                    {errors['seo.canonical']}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-x-6 gap-y-2">
                            <Toggle
                                label={t(
                                    'Ukryj przed wyszukiwarkami (noindex)',
                                )}
                                checked={Boolean(seo.noindex)}
                                onChange={(v) => onChange({ noindex: v })}
                            />
                            <Toggle
                                label={t('Nie podążaj za linkami (nofollow)')}
                                checked={Boolean(seo.nofollow)}
                                onChange={(v) => onChange({ nofollow: v })}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="seo-og-title">
                                {t('Tytuł w mediach społecznościowych')}
                            </Label>
                            <Input
                                id="seo-og-title"
                                value={str(seo.og_title)}
                                placeholder={shownTitle}
                                onChange={(e) =>
                                    onChange({ og_title: e.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="seo-og-description">
                                {t('Opis w mediach społecznościowych')}
                            </Label>
                            <textarea
                                id="seo-og-description"
                                rows={2}
                                className={nativeClass}
                                value={str(seo.og_description)}
                                placeholder={shownDescription}
                                onChange={(e) =>
                                    onChange({ og_description: e.target.value })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Obraz do udostępniania')}</Label>
                            {ogImage && (
                                <div className="relative w-full max-w-xs overflow-hidden rounded-lg border">
                                    <img
                                        src={ogImage.url}
                                        alt={ogImage.alt}
                                        className="w-full"
                                    />
                                    <button
                                        type="button"
                                        aria-label={t('Usuń obraz')}
                                        onClick={() =>
                                            onChange({ og_image_id: null })
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
                                onClick={() => setPicking(true)}
                            >
                                <ImagePlus />{' '}
                                {ogImage
                                    ? t('Zmień obraz')
                                    : t('Wybierz obraz')}
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Bez wyboru użyjemy obrazu wyróżniającego, a potem domyślnego z ustawień SEO.',
                                )}
                            </p>
                            <MediaPicker
                                open={picking}
                                onOpenChange={setPicking}
                                imagesOnly
                                onPick={(items) => {
                                    rememberMedia(items);
                                    onChange({
                                        og_image_id: items[0]?.id ?? null,
                                    });
                                }}
                            />
                        </div>
                    </div>
                </details>
            </CardContent>
        </Card>
    );
}
