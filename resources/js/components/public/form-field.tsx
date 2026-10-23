import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/*
 * Form fields on the public site's dark surfaces: a label, the control (given its id and
 * aria attributes), then a hint or the error.
 */

export const inputClass =
    'h-11 w-full min-w-0 rounded-md border border-white/15 bg-surface px-3 text-foreground placeholder:text-white/35 focus-visible:border-gold focus-visible:ring-2 focus-visible:ring-gold/40 focus-visible:outline-none aria-[invalid=true]:border-red-400';

export function Field({
    label,
    id: givenId,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    // Needed when two fields on a page share a label.
    id?: string;
    error?: string;
    hint?: string;
    className?: string;
    children: (
        id: string,
        describedBy: string | undefined,
        invalid: true | undefined,
    ) => ReactNode;
}) {
    const id =
        givenId ?? `field-${label.toLowerCase().replace(/[^a-z]+/g, '-')}`;
    const describedBy =
        [hint && `${id}-hint`, error && `${id}-error`]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={cn('grid min-w-0 gap-1.5', className)}>
            <label htmlFor={id} className="text-sm font-medium text-white/85">
                {label}
            </label>
            {children(id, describedBy, error ? true : undefined)}
            {hint && !error && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} className="text-sm text-red-400">
                    {error}
                </p>
            )}
        </div>
    );
}
