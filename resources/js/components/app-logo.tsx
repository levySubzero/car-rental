/**
 * Sidebar mark in the style of the public wordmark: a gold monogram badge with the
 * name set in tracked caps. The sidebar is ink in both modes, so no colour inversion.
 */
export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-base font-bold text-sidebar-primary-foreground">
                B
            </div>
            <div className="ml-1 grid flex-1 text-left leading-none">
                <span className="truncate text-sm font-bold tracking-[0.2em] text-white">
                    BAINES
                </span>
                <span className="mt-1 truncate text-[0.6rem] font-semibold tracking-[0.4em] text-sidebar-primary">
                    ADMIN
                </span>
            </div>
        </>
    );
}
