import { Head } from '@inertiajs/react';
import { MediaLibrary } from '@/components/cms/media-library';
import { t } from '@/lib/i18n';

export default function Library() {
    return (
        <>
            <Head title={t('Media')} />
            <div className="flex h-[calc(100svh-4rem)] flex-1 flex-col gap-4 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Media')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'Obrazy są automatycznie konwertowane do WebP w wielu rozmiarach.',
                        )}
                    </p>
                </div>
                <MediaLibrary mode="manage" className="min-h-0 flex-1" />
            </div>
        </>
    );
}
