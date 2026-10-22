import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function SectionHeading({
    eyebrow,
    title,
    children,
    align = 'left',
    className,
    id,
}: {
    eyebrow?: string;
    title: string;
    children?: ReactNode;
    align?: 'left' | 'center';
    className?: string;
    id?: string;
}) {
    return (
        <div
            className={cn(
                'max-w-2xl',
                align === 'center' && 'mx-auto text-center',
                className,
            )}
        >
            {eyebrow && (
                <p className="mb-3 eyebrow text-accent-gold">{eyebrow}</p>
            )}
            <h2
                id={id}
                className="font-display text-3xl leading-[1.1] text-foreground sm:text-4xl"
            >
                {title}
            </h2>
            {children && (
                <div className="mt-5 text-base leading-relaxed text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}
