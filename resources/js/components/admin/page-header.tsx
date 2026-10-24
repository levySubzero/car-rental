import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="space-y-1">
                <h1 className="font-display text-2xl">{title}</h1>
                {description && (
                    <p className="text-base text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function AdminPage({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
            {children}
        </div>
    );
}
