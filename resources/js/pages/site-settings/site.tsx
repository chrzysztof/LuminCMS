import { Head, router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cmsUrl } from '@/lib/cms-urls';

type Site = {
    ga_id: string;
    gtm_id: string;
    pixel_id: string;
    consent_required: boolean;
    privacy_url: string;
    track_logged_in: boolean;
    facebook: string;
    instagram: string;
    x: string;
    youtube: string;
    linkedin: string;
    tiktok: string;
    custom_head: string;
    custom_body_end: string;
};

const nativeClass =
    'border-input bg-transparent flex w-full rounded-md border px-3 py-2 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

const social: [keyof Site, string, string][] = [
    ['facebook', 'Facebook', 'https://facebook.com/…'],
    ['instagram', 'Instagram', 'https://instagram.com/…'],
    ['x', 'X / Twitter', 'https://x.com/…'],
    ['youtube', 'YouTube', 'https://youtube.com/@…'],
    ['linkedin', 'LinkedIn', 'https://linkedin.com/company/…'],
    ['tiktok', 'TikTok', 'https://tiktok.com/@…'],
];

export default function SiteSettings({ site }: { site: Site }) {
    const [data, setData] = useState<Site>(site);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const set = <K extends keyof Site>(key: K, value: Site[K]) =>
        setData((d) => ({ ...d, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(
            cmsUrl.siteSettings,
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

    const text = (
        key: keyof Site,
        label: string,
        placeholder: string,
        hint?: string,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`site-${key}`}>{label}</Label>
            <Input
                id={`site-${key}`}
                value={String(data[key])}
                placeholder={placeholder}
                onChange={(e) => set(key, e.target.value as never)}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {errors[key] && (
                <p className="text-xs text-destructive">{errors[key]}</p>
            )}
        </div>
    );

    const check = (
        key: 'consent_required' | 'track_logged_in',
        label: string,
        hint: string,
    ) => (
        <label className="flex items-start gap-2 text-sm">
            <input
                type="checkbox"
                className="mt-0.5 size-4 accent-brand"
                checked={data[key]}
                onChange={(e) => set(key, e.target.checked)}
            />
            <span>
                {label}
                <span className="block text-xs text-muted-foreground">
                    {hint}
                </span>
            </span>
        </label>
    );

    return (
        <>
            <Head title="Ustawienia witryny" />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Ustawienia witryny
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Analityka, piksele, profile społecznościowe i własny
                            kod.
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
                            <CardTitle className="text-base">
                                Analityka i piksele
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {text(
                                'ga_id',
                                'Google Analytics 4',
                                'G-XXXXXXXXXX',
                            )}
                            {text(
                                'gtm_id',
                                'Google Tag Manager',
                                'GTM-XXXXXXX',
                            )}
                            {text(
                                'pixel_id',
                                'Pixel Meta (Facebook)',
                                '123456789012345',
                            )}
                            {check(
                                'consent_required',
                                'Wymagaj zgody na cookies',
                                'Skrypty ładują się dopiero po kliknięciu „Akceptuję” w banerze; w stopce pojawia się link do zmiany decyzji.',
                            )}
                            {text(
                                'privacy_url',
                                'Polityka prywatności',
                                '/polityka-prywatnosci',
                                'Link w banerze zgody (ścieżka albo pełny adres).',
                            )}
                            {check(
                                'track_logged_in',
                                'Śledź także zalogowanych użytkowników',
                                'Domyślnie wyłączone, żeby Twoja praca w panelu nie zaburzała statystyk.',
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Media społecznościowe
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {social.map(([key, label, placeholder]) => (
                                <div key={key}>
                                    {text(key, label, placeholder)}
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                Adresy trafiają do danych strukturalnych
                                (JSON-LD) jako profile organizacji.
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Własny kod
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                Kod jest wstawiany bez zmian na każdej
                                publicznej stronie. Wklejaj tylko zaufane
                                skrypty; ten kod nie czeka na zgodę na cookies.
                            </p>
                            <div className="grid gap-1.5">
                                <Label htmlFor="site-custom_head">
                                    Kod w &lt;head&gt;
                                </Label>
                                <textarea
                                    id="site-custom_head"
                                    rows={5}
                                    className={nativeClass}
                                    value={data.custom_head}
                                    onChange={(e) =>
                                        set('custom_head', e.target.value)
                                    }
                                />
                                {errors.custom_head && (
                                    <p className="text-xs text-destructive">
                                        {errors.custom_head}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="site-custom_body_end">
                                    Kod przed &lt;/body&gt;
                                </Label>
                                <textarea
                                    id="site-custom_body_end"
                                    rows={5}
                                    className={nativeClass}
                                    value={data.custom_body_end}
                                    onChange={(e) =>
                                        set('custom_body_end', e.target.value)
                                    }
                                />
                                {errors.custom_body_end && (
                                    <p className="text-xs text-destructive">
                                        {errors.custom_body_end}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </form>
        </>
    );
}
