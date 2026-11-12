import { cn } from '@/lib/utils';
import type { ContentStatus } from '@/types/cms';

const styles: Record<ContentStatus, { label: string; className: string }> = {
    published: {
        label: 'Opublikowana',
        className:
            'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    },
    draft: {
        label: 'Szkic',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    pending: {
        label: 'Oczekuje na przegląd',
        className: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    scheduled: {
        label: 'Zaplanowana',
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
