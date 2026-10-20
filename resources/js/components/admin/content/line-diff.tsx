import { diffLines } from 'diff';
import { useMemo } from 'react';
import { cn } from '@/lib/utils';

/**
 * Line-by-line changes between two texts: removed lines in red, added lines in green.
 */
export function LineDiff({
    before,
    after,
    className,
}: {
    before: string;
    after: string;
    className?: string;
}) {
    const parts = useMemo(() => diffLines(before, after), [before, after]);
    const changed = parts.some((part) => part.added || part.removed);

    if (!changed) {
        return <p className="text-sm text-muted-foreground">No changes.</p>;
    }

    return (
        <pre
            className={cn(
                'max-h-[32rem] overflow-auto rounded-md border bg-muted/30 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap',
                className,
            )}
        >
            {parts.map((part, index) => (
                <span
                    key={index}
                    className={cn(
                        'block',
                        part.added &&
                            'bg-emerald-500/15 text-emerald-900 dark:text-emerald-200',
                        part.removed &&
                            'bg-red-500/15 text-red-900 line-through decoration-red-500/40 dark:text-red-200',
                        !part.added && !part.removed && 'text-muted-foreground',
                    )}
                >
                    {part.value
                        .replace(/\n$/, '')
                        .split('\n')
                        .map(
                            (line) =>
                                `${part.added ? '+ ' : part.removed ? '- ' : '  '}${line}`,
                        )
                        .join('\n')}
                </span>
            ))}
        </pre>
    );
}
