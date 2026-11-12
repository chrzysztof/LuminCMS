import { Head, router } from '@inertiajs/react';
import { ImagePlus, Loader2, X } from 'lucide-react';
import { useState } from 'react';
import { MediaPicker } from '@/components/cms/media-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';
import { applyTemplate } from '@/lib/seo';
import type { MediaItem } from '@/types/cms';

type Seo = {
    site_name: string;
    tagline: string;
    description: string;
    title_template: string;
    separator: string;
    default_og_image_id: number | null;
    twitter_handle: string;
    noindex_site: boolean;
};

type Props = {
    seo: Seo;
    defaultImage: MediaItem | null;
    sitemapUrl: string;
    robotsUrl: string;
};

const nativeClass =
    'border-input bg-transparent flex w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

export default function SeoSettings({
    seo,
    defaultImage,
    sitemapUrl,
    robotsUrl,
}: Props) {
    const [data, setData] = useState<Seo>(seo);
    const [image, setImage] = useState<MediaItem | null>(defaultImage);
    const [picking, setPicking] = useState(false);
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
            <Head title="Ustawienia SEO" />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Ustawienia SEO
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Wartości domyślne dla całej witryny.
                        </p>
                    </div>
                    <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="animate-spin" />}{' '}
                        Zapisz
                    </Button>
                </div>

                <div className="grid max-w-3xl gap-5">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">Witryna</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {field(
                                'site_name',
                                'Nazwa witryny',
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
                                'Slogan',
                                'Używany w tytule strony głównej.',
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
                                'Domyślny opis',
                                'Gdy treść nie ma własnego opisu ani zajawki.',
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
                                Tytuły stron
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {field(
                                'title_template',
                                'Szablon tytułu',
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
                                'Separator',
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
                                Przykład:{' '}
                                <strong>
                                    {applyTemplate(
                                        data.title_template,
                                        'O nas',
                                        data,
                                    )}
                                </strong>
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Media społecznościowe
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
                            <div className="space-y-2">
                                <Label>Domyślny obraz do udostępniania</Label>
                                {image && (
                                    <div className="relative w-full max-w-xs overflow-hidden rounded-lg border">
                                        <img
                                            src={image.url}
                                            alt={image.alt}
                                            className="w-full"
                                        />
                                        <button
                                            type="button"
                                            aria-label="Usuń obraz"
                                            onClick={() => {
                                                setImage(null);
                                                set(
                                                    'default_og_image_id',
                                                    null,
                                                );
                                            }}
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
                                    {image ? 'Zmień obraz' : 'Wybierz obraz'}
                                </Button>
                                <MediaPicker
                                    open={picking}
                                    onOpenChange={setPicking}
                                    imagesOnly
                                    onPick={(items) => {
                                        setImage(items[0] ?? null);
                                        set(
                                            'default_og_image_id',
                                            items[0]?.id ?? null,
                                        );
                                    }}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Indeksowanie
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
                                    Zniechęcaj wyszukiwarki do indeksowania
                                    witryny
                                    <span className="block text-xs text-muted-foreground">
                                        Przydatne podczas budowy strony: dodaje
                                        noindex, blokuje robots.txt i opróżnia
                                        sitemapę.
                                    </span>
                                </span>
                            </label>
                            <p className="text-sm text-muted-foreground">
                                Mapa witryny:{' '}
                                <a
                                    className="underline"
                                    href={sitemapUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {sitemapUrl}
                                </a>
                                <br />
                                Robots:{' '}
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
