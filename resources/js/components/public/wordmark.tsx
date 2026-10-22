import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

/**
 * Text wordmark until the client supplies the vector logo.
 */
export function Wordmark({ className }: { className?: string }) {
    return (
        <Link
            href={home()}
            className={cn('group inline-flex flex-col leading-none', className)}
            aria-label="Baines Executives, home"
        >
            <span className="text-2xl font-bold tracking-[0.2em] text-white transition-colors group-hover:text-gold-light">
                BAINES
            </span>
            <span className="mt-1 text-[0.6rem] font-semibold tracking-[0.5em] text-gold">
                EXECUTIVES
            </span>
        </Link>
    );
}
