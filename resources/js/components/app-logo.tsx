import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { cmsVersion } = usePage<{ cmsVersion?: string }>().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
                L
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    Lumin
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    CMS {cmsVersion ? `v${cmsVersion}` : ''}
                </span>
            </div>
        </>
    );
}
