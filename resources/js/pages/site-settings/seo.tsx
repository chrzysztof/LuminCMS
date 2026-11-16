import { Head, router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { ImageField } from '@/components/cms/image-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { applyTemplate } from '@/lib/seo';
import type { MediaItem } from '@/types/cms';
import { t } from '@/lib/i18n';

type Seo = {
    site_name: string;
    tagline: string;
    description: string;
    title_template: string;
    separator: string;
    default_og_image_id: number | null;
    logo_id: number | null;
    favicon_id: number | null;
    twitter_handle: string;
    noindex_site: boolean;
};

type Props = {
    seo: Seo;
    defaultImage: MediaItem | null;
    logo: MediaItem | null;
    favicon: MediaItem | null;
    sitemapUrl: string;
    robotsUrl: string;
};

const nativeClass =
    'border-input bg-transparent flex w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function SeoSettings({
    seo,
    defaultImage,
    logo: initialLogo,
    favicon: initialFavicon,
    sitemapUrl,
    robotsUrl,
}: Props) {
    const [data, setData] = useState<Seo>(seo);
    const [image, setImage] = useState<MediaItem | null>(defaultImage);
    const [logo, setLogo] = useState<MediaItem | null>(initialLogo);
    const [favicon, setFavicon] = useState<MediaItem | null>(initialFavicon);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const set = <K extends keyof Seo>(key: K, value: Seo[K]) =>
        setData((d) => ({ ...d, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(
            cmsUrl.seoSettings,
            data as unknown as Record<string, never>,
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errs) => setErrors(errs),
                onSuccess: () => setErrors({}),
            },
        );
    };

    const field = (
        key: keyof Seo,
        label: string,
        hint?: string,
        node?: React.ReactNode,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`s-${key}`}>{label}</Label>
            {node}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {errors[key] && (
                <p className="text-xs text-destructive">{errors[key]}</p>
            )}
        </div>
    );

    return (
        <>
            <Head title={t('Ustawienia SEO')} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:px-6 md:pt-5 md:pb-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-[26px] leading-tight font-bold tracking-tight">
                            {t('Ustawienia SEO')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('Wartości domyślne dla całej witryny.')}
                        </p>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="animate-spin" />}{' '}
                        {t('Zapisz')}
                    </Button>
                </div>

                <div className="grid max-w-3xl gap-5">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Tożsamość witryny')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <ImageField
                                label={t('Logo')}
                                hint={t(
                                    'Wyświetlane w nagłówku zamiast nazwy witryny. Najlepiej PNG lub WebP z przezroczystym tłem.',
                                )}
                                value={logo}
                                previewClassName="h-14 w-auto"
                                error={errors.logo_id}
                                onChange={(item) => {
                                    setLogo(item);
                                    set('logo_id', item?.id ?? null);
                                }}
                            />
                            <ImageField
                                label={t('Favicon')}
                                hint={t(
                                    'Ikona w karcie przeglądarki. Kwadratowy obraz PNG, najlepiej 512 × 512 px.',
                                )}
                                value={favicon}
                                previewClassName="size-14 object-contain"
                                error={errors.favicon_id}
                                onChange={(item) => {
                                    setFavicon(item);
                                    set('favicon_id', item?.id ?? null);
                                }}
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Witryna')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {field(
                                'site_name',
                                t('Nazwa witryny'),
                                undefined,
                                <Input
                                    id="s-site_name"
                                    value={data.site_name}
                                    onChange={(e) =>
                                        set('site_name', e.target.value)
                                    }
                                />,
                            )}
                            {field(
                                'tagline',
                                t('Slogan'),
                                t('Używany w tytule strony głównej.'),
                                <Input
                                    id="s-tagline"
                                    value={data.tagline}
                                    onChange={(e) =>
                                        set('tagline', e.target.value)
                                    }
                                />,
                            )}
                            {field(
                                'description',
                                t('Domyślny opis'),
                                t(
                                    'Gdy treść nie ma własnego opisu ani zajawki.',
                                ),
                                <textarea
                                    id="s-description"
                                    rows={3}
                                    className={nativeClass}
                                    value={data.description}
                                    onChange={(e) =>
                                        set('description', e.target.value)
                                    }
                                />,
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Tytuły stron')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {field(
                                'title_template',
                                t('Szablon tytułu'),
                                'Znaczniki: %title%, %site%, %tagline%, %sep%.',
                                <Input
                                    id="s-title_template"
                                    value={data.title_template}
                                    onChange={(e) =>
                                        set('title_template', e.target.value)
                                    }
                                />,
                            )}
                            {field(
                                'separator',
                                t('Separator'),
                                undefined,
                                <Input
                                    id="s-separator"
                                    className="w-24"
                                    value={data.separator}
                                    onChange={(e) =>
                                        set('separator', e.target.value)
                                    }
                                />,
                            )}
                            <p className="rounded-md bg-muted/60 p-3 text-sm">
                                {t('Przykład:')}{' '}
                                <strong>
                                    {applyTemplate(
                                        data.title_template,
                                        t('O nas'),
                                        data,
                                    )}
                                </strong>
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Media społecznościowe')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {field(
                                'twitter_handle',
                                'Konto X / Twitter',
                                'Np. @mojafirma',
                                <Input
                                    id="s-twitter_handle"
                                    className="max-w-xs"
                                    value={data.twitter_handle}
                                    onChange={(e) =>
                                        set('twitter_handle', e.target.value)
                                    }
                                />,
                            )}
                            <ImageField
                                label={t('Domyślny obraz do udostępniania')}
                                value={image}
                                error={errors.default_og_image_id}
                                onChange={(item) => {
                                    setImage(item);
                                    set(
                                        'default_og_image_id',
                                        item?.id ?? null,
                                    );
                                }}
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Indeksowanie')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 size-4 accent-brand"
                                    checked={data.noindex_site}
                                    onChange={(e) =>
                                        set('noindex_site', e.target.checked)
                                    }
                                />
                                <span>
                                    {t(
                                        'Zniechęcaj wyszukiwarki do indeksowania witryny',
                                    )}
                                    <span className="block text-xs text-muted-foreground">
                                        {t(
                                            'Przydatne podczas budowy strony: dodaje noindex, blokuje robots.txt i opróżnia sitemapę.',
                                        )}
                                    </span>
                                </span>
                            </label>
                            <p className="text-sm text-muted-foreground">
                                {t('Mapa witryny:')}{' '}
                                <a
                                    className="underline"
                                    href={sitemapUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {sitemapUrl}
                                </a>
                                <br />
                                {t('Robots:')}{' '}
                                <a
                                    className="underline"
                                    href={robotsUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {robotsUrl}
                                </a>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}
