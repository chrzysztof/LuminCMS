import { cn } from '@/lib/utils';
import type { ContentStatus } from '@/types/cms';
import { t } from '@/lib/i18n';

const styles: Record<ContentStatus, { label: string; className: string }> = {
    published: {
        label: t('Opublikowana'),
        className:
            'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    },
    draft: {
        label: t('Szkic'),
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    pending: {
        label: t('Oczekuje na przegląd'),
        className: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    scheduled: {
        label: t('Zaplanowana'),
        className: 'bg-brand-soft text-brand-foreground',
    },
};

export function StatusBadge({ status }: { status: ContentStatus }) {
    const s = styles[status] ?? styles.draft;

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                s.className,
            )}
        >
            {s.label}
        </span>
    );
}
