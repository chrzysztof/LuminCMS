import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { t } from '@/lib/i18n';

type Shared = { locale: string; locales: Record<string, string> };

/** Wybór języka panelu; po zmianie przeładowujemy stronę, aby wczytać nowy słownik. */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { locale, locales } = usePage<Shared>().props;

    return (
        <label className={className}>
            <span className="sr-only">{t('Language')}</span>
            <span className="flex items-center gap-2">
                <Languages className="size-4 text-muted-foreground" />
                <select
                    aria-label={t('Language')}
                    value={locale}
                    className="flex h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30"
                    onChange={(e) =>
                        router.post(
                            '/locale',
                            { locale: e.target.value },
                            {
                                preserveScroll: true,
                                onSuccess: () => window.location.reload(),
                            },
                        )
                    }
                >
                    {Object.entries(locales).map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
            </span>
        </label>
    );
}
