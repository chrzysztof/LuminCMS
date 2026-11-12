export default function AppLogo() {
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
                    CMS v0.1
                </span>
            </div>
        </>
    );
}
