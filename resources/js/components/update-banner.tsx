import { Link, usePage } from '@inertiajs/react';
import { ArrowUpCircle } from 'lucide-react';
import { cmsUrl } from '@/lib/cms-urls';
import { t } from '@/lib/i18n';

type Shared = { cmsUpdate?: { version: string; current: string } | null };

/** Pasek dla administratorów: jest nowsza wersja systemu. */
export function UpdateBanner() {
    const { cmsUpdate } = usePage<Shared & Record<string, unknown>>().props;

    if (!cmsUpdate) {
        return null;
    }

    return (
        <div className="flex items-center gap-2 border-b bg-foreground px-4 py-2 text-sm text-background">
            <ArrowUpCircle className="size-4 shrink-0" />
            <span className="min-w-0 flex-1 truncate">
                {t('Dostępna jest nowa wersja Lumin CMS')}{' '}
                <strong>{cmsUpdate.version}</strong> ({t('masz')}{' '}
                {cmsUpdate.current})
            </span>
            <Link
                href={cmsUrl.updates}
                className="shrink-0 font-medium underline underline-offset-2"
            >
                {t('Zobacz i zaktualizuj')}
            </Link>
        </div>
    );
}
